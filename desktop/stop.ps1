# Stops the TDS School local server started by launch.ps1
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$php = (Join-Path $root 'php\php.exe')
Get-Process -Name php -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $php } | Stop-Process -Force -ErrorAction SilentlyContinue
