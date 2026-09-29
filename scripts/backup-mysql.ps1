param(
    [Parameter(Mandatory=$true)][string]$OutputDirectory,
    [Parameter(Mandatory=$true)][string]$EncryptionPasswordFile,
    [string]$MySqlDump = 'mysqldump'
)

$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $EncryptionPasswordFile -PathType Leaf)) { throw 'Encryption password file does not exist.' }
if (-not $env:DB_HOST -or -not $env:DB_DATABASE -or -not $env:DB_USERNAME) { throw 'DB_HOST, DB_DATABASE and DB_USERNAME are required.' }
New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$plain = Join-Path $OutputDirectory ("mypos-$stamp.sql")
$encrypted = "$plain.enc"
$defaults = Join-Path ([System.IO.Path]::GetTempPath()) ("mypos-backup-$([guid]::NewGuid()).cnf")
try {
    @("[client]", "host=$env:DB_HOST", "port=$env:DB_PORT", "user=$env:DB_USERNAME", "password=$env:DB_PASSWORD") | Set-Content -LiteralPath $defaults -Encoding utf8NoBOM
    & $MySqlDump "--defaults-extra-file=$defaults" --single-transaction --no-tablespaces --routines --triggers --events --set-gtid-purged=OFF --result-file=$plain $env:DB_DATABASE
    if ($LASTEXITCODE -ne 0) { throw "mysqldump failed with exit code $LASTEXITCODE." }
    $password = [System.IO.File]::ReadAllText((Resolve-Path -LiteralPath $EncryptionPasswordFile)).TrimEnd("`r", "`n")
    if ($password.Length -lt 16) { throw 'Encryption password must contain at least 16 characters.' }
    $salt = [byte[]]::new(16); [System.Security.Cryptography.RandomNumberGenerator]::Fill($salt)
    $aes = [System.Security.Cryptography.Aes]::Create(); $aes.GenerateIV()
    $derive = [System.Security.Cryptography.Rfc2898DeriveBytes]::new($password, $salt, 200000, [System.Security.Cryptography.HashAlgorithmName]::SHA256)
    $aes.Key = $derive.GetBytes(32)
    $input = [System.IO.File]::OpenRead($plain); $output = [System.IO.File]::Create($encrypted)
    try {
        $header = [System.Text.Encoding]::ASCII.GetBytes('MYPOSBK1'); $output.Write($header); $output.Write($salt); $output.Write($aes.IV)
        $crypto = [System.Security.Cryptography.CryptoStream]::new($output, $aes.CreateEncryptor(), [System.Security.Cryptography.CryptoStreamMode]::Write, $true)
        try { $input.CopyTo($crypto); $crypto.FlushFinalBlock() } finally { $crypto.Dispose() }
    } finally { $input.Dispose(); $output.Dispose(); $derive.Dispose(); $aes.Dispose() }
    $hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $encrypted).Hash.ToLowerInvariant()
    @{ created_at=(Get-Date).ToUniversalTime().ToString('o'); database=$env:DB_DATABASE; sha256=$hash; encryption='AES-256-CBC/PBKDF2-SHA256-200000'; file=(Split-Path -Leaf $encrypted) } | ConvertTo-Json | Set-Content -LiteralPath "$encrypted.json" -Encoding utf8NoBOM
    Write-Output $encrypted
} finally {
    Remove-Item -LiteralPath $defaults -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $plain -Force -ErrorAction SilentlyContinue
    Remove-Variable password -ErrorAction SilentlyContinue
}
