param(
    [Parameter(Mandatory=$true)][string]$EncryptedBackup,
    [Parameter(Mandatory=$true)][string]$EncryptionPasswordFile,
    [Parameter(Mandatory=$true)][string]$TargetDatabase,
    [Parameter(Mandatory=$true)][string]$ExpectedSha256,
    [string]$MySql = 'mysql'
)

$ErrorActionPreference = 'Stop'
if ($TargetDatabase -notmatch '_restore_verify$') { throw 'Safety rule: target database name must end in _restore_verify.' }
if ((Get-FileHash -Algorithm SHA256 -LiteralPath $EncryptedBackup).Hash -ne $ExpectedSha256) { throw 'Backup SHA-256 does not match.' }
$plain = Join-Path ([System.IO.Path]::GetTempPath()) ("mypos-restore-$([guid]::NewGuid()).sql")
$defaults = Join-Path ([System.IO.Path]::GetTempPath()) ("mypos-restore-$([guid]::NewGuid()).cnf")
try {
    $password = [System.IO.File]::ReadAllText((Resolve-Path -LiteralPath $EncryptionPasswordFile)).TrimEnd("`r", "`n")
    $input = [System.IO.File]::OpenRead((Resolve-Path -LiteralPath $EncryptedBackup)); $header = [byte[]]::new(8); $salt = [byte[]]::new(16); $iv = [byte[]]::new(16)
    $null = $input.Read($header); $null = $input.Read($salt); $null = $input.Read($iv)
    if ([System.Text.Encoding]::ASCII.GetString($header) -ne 'MYPOSBK1') { $input.Dispose(); throw 'Unsupported encrypted backup format.' }
    $aes = [System.Security.Cryptography.Aes]::Create(); $derive = [System.Security.Cryptography.Rfc2898DeriveBytes]::new($password, $salt, 200000, [System.Security.Cryptography.HashAlgorithmName]::SHA256); $aes.Key = $derive.GetBytes(32); $aes.IV = $iv
    $output = [System.IO.File]::Create($plain)
    try {
        $crypto = [System.Security.Cryptography.CryptoStream]::new($input, $aes.CreateDecryptor(), [System.Security.Cryptography.CryptoStreamMode]::Read)
        try { $crypto.CopyTo($output) } finally { $crypto.Dispose() }
    } finally { $input.Dispose(); $output.Dispose(); $derive.Dispose(); $aes.Dispose() }
    @("[client]", "host=$env:DB_HOST", "port=$env:DB_PORT", "user=$env:DB_USERNAME", "password=$env:DB_PASSWORD") | Set-Content -LiteralPath $defaults -Encoding utf8NoBOM
    & $MySql "--defaults-extra-file=$defaults" -e "CREATE DATABASE IF NOT EXISTS ``$TargetDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    if ($LASTEXITCODE -ne 0) { throw 'Target database creation failed.' }
    $process = Start-Process -FilePath $MySql -ArgumentList @("--defaults-extra-file=$defaults", $TargetDatabase) -RedirectStandardInput $plain -NoNewWindow -Wait -PassThru
    if ($process.ExitCode -ne 0) { throw "Restore failed with exit code $($process.ExitCode)." }
    Write-Output "Restored into isolated database $TargetDatabase"
} finally {
    Remove-Item -LiteralPath $defaults -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $plain -Force -ErrorAction SilentlyContinue
    Remove-Variable password -ErrorAction SilentlyContinue
}
