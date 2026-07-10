# Renderiza um .pptx em PNGs via PowerPoint COM (app-alvo real → render fiel).
# Uso: powershell -File render.ps1 <caminho.pptx> <pasta_saida>
param(
  [Parameter(Mandatory=$true)][string]$Pptx,
  [Parameter(Mandatory=$true)][string]$OutDir
)
$ErrorActionPreference = "Stop"
$Pptx = (Resolve-Path $Pptx).Path
if (Test-Path $OutDir) { Remove-Item "$OutDir\*.png" -Force -ErrorAction SilentlyContinue }
else { New-Item -ItemType Directory -Path $OutDir -Force | Out-Null }
$OutDir = (Resolve-Path $OutDir).Path

$pp = New-Object -ComObject PowerPoint.Application
try {
  # WithWindow=$false falha em algumas versões; abrimos read-only
  $pres = $pp.Presentations.Open($Pptx, $true, $false, $false)
  # Export de todos os slides como PNG (largura x altura em px, 16:9)
  $pres.Export($OutDir, "PNG", 1600, 900)
  $pres.Close()
} finally {
  $pp.Quit()
  [System.Runtime.InteropServices.Marshal]::ReleaseComObject($pp) | Out-Null
}
$imgs = Get-ChildItem "$OutDir\*.png" | Sort-Object Name
"Exportados $($imgs.Count) slide(s) para $OutDir"
$imgs | ForEach-Object { $_.FullName }