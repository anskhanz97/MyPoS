<?php

namespace App\Domain\Reporting;

use App\Logging\OperationalLogger;
use App\Models\DataTransferJob;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class ReportExporter
{
    public function __construct(private readonly ReportQuery $query, private readonly ReportAccess $access, private readonly OperationalLogger $logs) {}

    public function execute(DataTransferJob $job): void
    {
        $options = $job->optionsData();
        $temporary = tempnam(sys_get_temp_dir(), 'mypos-report-');
        abort_unless(is_string($temporary), 500, 'Could not create export file.');
        $writer = null;
        $stream = null;
        try {
            $merchant = Merchant::query()->findOrFail($job->merchant_id);
            $store = $job->store_id === null ? null : Store::query()->where('merchant_id', $merchant->id)->findOrFail($job->store_id);
            $actor = User::query()->where('is_active', true)->findOrFail($job->actor_user_id);
            $scope = $this->access->authorize($actor, $merchant, $store, $options['report_key'], true);
            $job->update(['state' => 'processing', 'failure_message' => null]);
            $this->logs->application('report.export.started', 'Report export started.', ['merchant_id' => $merchant->id, 'store_id' => $store?->id, 'operation_id' => 'export:'.$job->public_id]);
            $result = $this->query->readSnapshot(function () use ($merchant, $store, $options, $scope, $temporary, &$writer, &$stream): array {
                $filters = array_replace($options['filters'], ['per_page' => 1, 'page' => 1]);
                $report = $this->query->run($merchant, $store, $options['report_key'], $filters, $scope);
                $total = $report['pagination']['total'];
                abort_if($total > 1000000, 422, 'This export exceeds one million rows. Narrow the report filters.');
                abort_if($options['format'] === 'pdf' && ! ($options['summary_only'] ?? false) && $total > 2000, 422, 'PDF detail is limited to 2,000 rows. Choose summary PDF or narrow the filters.');
                $columns = $report['columns'];
                if ($options['format'] === 'pdf') {
                    $columns = array_values(array_filter($columns, fn ($column) => ! in_array($column['key'], ['group_key', 'document_id', 'line_id', 'source_id', 'supplier_id', 'payload', 'content_hash'], true)));
                    $report['columns'] = $columns;
                }
                $headers = array_column($columns, 'label');
                if ($options['format'] === 'csv') {
                    $stream = fopen($temporary, 'wb');
                    abort_unless(is_resource($stream), 500);
                    fwrite($stream, "\xEF\xBB\xBF");
                    fputcsv($stream, $headers, escape: '');
                } elseif ($options['format'] === 'xlsx') {
                    $writer = new Writer;
                    $writer->openToFile($temporary);
                    $writer->getCurrentSheet()->setName('Information');
                    foreach (['Report' => $report['title'], 'Generated at' => $report['freshness']['generated_at'], 'Definition version' => 2, 'Scope' => $report['scope'], 'Store' => $report['period']['store'], 'Timezone' => $report['period']['timezone'], 'Start' => $filters['start'], 'End' => $filters['end']] + $report['summary'] as $label => $value) {
                        $writer->addRow($this->xlsxRow([$label, $value === null ? 'Unavailable' : (string) $value]));
                    }
                    foreach ($report['definitions'] as $definition) {
                        $writer->addRow($this->xlsxRow(['Definition', $definition]));
                    }
                    $writer->addNewSheetAndMakeItCurrent()->setName('Detail 1');
                    $writer->addRow($this->xlsxRow($headers, (new Style)->setFontBold()));
                }
                $query = $this->query->order($this->query->rows($merchant, $store, $options['report_key'], $filters, $scope), $filters['sort'] ?? ($options['report_key'] === 'top-bottom-sellers' ? 'revenue' : 'label'), $filters['direction'] ?? ($options['report_key'] === 'top-bottom-sellers' ? 'desc' : 'asc'), $report['summary']);
                $pdfRows = [];
                $count = 0;
                $numberStyles = ['money' => (new Style)->setFormat('0.00'), 'decimal' => (new Style)->setFormat('0.######'), 'integer' => (new Style)->setFormat('0'), 'percentage' => (new Style)->setFormat('0.00%')];
                if (! ($options['summary_only'] ?? false) || $options['format'] !== 'pdf') {
                    $pdo = DB::connection()->getPdo();
                    $mysql = DB::getDriverName() === 'mysql';
                    if ($mysql) {
                        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                    }
                    try {
                        foreach ($query->cursor() as $record) {
                            $row = $this->query->present((array) $record, $columns, $report['summary']['revenue'] ?? null);
                            $values = array_values($row);
                            if ($options['format'] === 'csv') {
                                fputcsv($stream, array_map(fn ($value, $column) => $this->csvCell($value, $column['type']), $values, $columns), escape: '');
                            } elseif ($options['format'] === 'xlsx') {
                                if ($count > 0 && $count % 100000 === 0) {
                                    $writer->addNewSheetAndMakeItCurrent()->setName('Detail '.((int) ($count / 100000) + 1));
                                    $writer->addRow($this->xlsxRow($headers, (new Style)->setFontBold()));
                                }
                                $styles = [];
                                foreach ($columns as $index => $column) {
                                    $value = $values[$index];
                                    if ($value !== null && isset($numberStyles[$column['type']]) && is_numeric($value) && strlen(preg_replace('/[^0-9]/', '', (string) $value)) <= 15) {
                                        $values[$index] = $column['type'] === 'percentage' ? (float) $value / 100 : (float) $value;
                                        $styles[$index] = $numberStyles[$column['type']];
                                    } elseif ($value !== null) {
                                        $values[$index] = (string) $value;
                                    }
                                }
                                $writer->addRow($this->xlsxRow($values, columnStyles: $styles));
                            } else {
                                $pdfRows[] = $row;
                            }
                            $count++;
                        }
                    } finally {
                        if ($mysql) {
                            $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                        }
                    }
                }
                if ($options['format'] === 'pdf') {
                    $dompdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);
                    $dompdf->loadHtml(view('reports.export', ['report' => $report, 'rows' => $pdfRows, 'merchantName' => $merchant->name])->render());
                    $dompdf->setPaper('A4', count($columns) > 7 ? 'landscape' : 'portrait');
                    $dompdf->render();
                    file_put_contents($temporary, $dompdf->output());
                }

                return ['count' => $count, 'metadata' => ['definition_version' => 2, 'generated_at' => $report['freshness']['generated_at'], 'summary' => $report['summary'], 'row_count' => $total]];
            });
            if ($writer !== null) {
                $writer->close();
                $writer = null;
            }
            if (is_resource($stream)) {
                fclose($stream);
                $stream = null;
            }
            $path = 'merchants/'.$merchant->public_id.'/reports/'.$job->public_id.'.'.$options['format'];
            $file = fopen($temporary, 'rb');
            abort_unless(is_resource($file), 500);
            try {
                abort_unless(Storage::disk($job->disk)->put($path, $file) === true, 500, 'Could not store the report artifact.');
            } finally {
                fclose($file);
            }
            $job->update(['state' => 'ready', 'artifact_path' => $path, 'artifact_hash' => hash_file('sha256', $temporary), 'total_rows' => $result['metadata']['row_count'], 'options' => $options + ['result' => $result['metadata']], 'expires_at' => now()->addDays(7)]);
            $this->logs->application('report.export.completed', 'Report export is ready.', ['merchant_id' => $merchant->id, 'operation_id' => 'export:'.$job->public_id]);
        } catch (Throwable $e) {
            $message = $e instanceof HttpException ? $e->getMessage() : 'Report export failed. Review the export worker diagnostics.';
            $job->update(['state' => 'failed', 'failure_message' => mb_substr($message, 0, 500)]);
            $this->logs->application('report.export.failed', 'Report export failed.', ['merchant_id' => $job->merchant_id, 'operation_id' => 'export:'.$job->public_id, 'status' => 'failed']);
            throw $e;
        } finally {
            if ($writer !== null) {
                $writer->close();
            }
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * @param  list<null|bool|int|float|string>  $values
     * @param  array<int, Style>  $columnStyles
     */
    private function xlsxRow(array $values, ?Style $rowStyle = null, array $columnStyles = []): Row
    {
        $cells = [];
        foreach ($values as $index => $value) {
            $style = $columnStyles[$index] ?? null;
            $cells[] = is_string($value) ? new StringCell($value, $style) : Cell::fromValue($value, $style);
        }

        return new Row($cells, $rowStyle);
    }

    private function csvCell(mixed $value, string $type): string
    {
        $value = (string) ($value ?? '');
        if (in_array($type, ['money', 'decimal', 'integer', 'percentage'], true) && is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
