Add-Type -AssemblyName System.Drawing

$src    = "C:\Users\baan\.gemini\antigravity\brain\7d174caf-b82f-45e6-84d6-a93c5c149dd0\kasir_pwa_icon_1778765866047.png"
$outDir = "C:\Users\baan\APP\kasir\assets\icons"

New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$orig = [System.Drawing.Image]::FromFile($src)

foreach ($size in @(192, 512)) {
    $bmp = New-Object System.Drawing.Bitmap($size, $size)
    $g   = [System.Drawing.Graphics]::FromImage($bmp)
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.DrawImage($orig, 0, 0, $size, $size)
    $g.Dispose()
    $out = Join-Path $outDir "icon-$size.png"
    $bmp.Save($out, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
    Write-Host "Saved: $out"
}

$orig.Dispose()
Write-Host "Done!"
