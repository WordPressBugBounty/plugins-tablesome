<?php

namespace Tablesome\Workflow_Library\Actions;

if (!defined('ABSPATH')) {
    exit;
}

require_once TABLESOME_PATH . 'includes/lib/fpdf/pdf-html.php';

/**
 * Extends the third-party PDF_HTML class with WordPress-specific
 * image handling: local file resolution, quality upgrade, configurable
 * width, aspect-ratio preservation, and proper page flow.
 */
class Tablesome_PDF_HTML extends \PDF_HTML
{
    protected $imageWidth = 300;
    protected $uploadBaseUrl = '';
    protected $uploadBaseUrlHttps = '';
    protected $uploadBaseDir = '';

    public function setImageWidth($px)
    {
        $this->imageWidth = max(50, min(700, intval($px)));
    }

    public function setUploadDir($upload_dir)
    {
        $this->uploadBaseUrl = set_url_scheme($upload_dir['baseurl'], 'http');
        $this->uploadBaseUrlHttps = set_url_scheme($upload_dir['baseurl'], 'https');
        $this->uploadBaseDir = $upload_dir['basedir'];
    }

    public function OpenTag($tag, $attr)
    {
        if ($tag !== 'IMG') {
            parent::OpenTag($tag, $attr);
            return;
        }

        if (!isset($attr['SRC'])) {
            return;
        }

        $hasWidth = isset($attr['WIDTH']) && $attr['WIDTH'] > 0;
        $maxWidth = $this->GetPageWidth() - $this->lMargin - $this->rMargin;

        $src = $this->resolveImageSrc($attr['SRC']);
        $imageSize = @getimagesize($src);

        // Determine target width in px:
        // - If width attribute set (e.g. signatures): use it
        // - Otherwise: use the configured default
        $targetPx = $hasWidth ? intval($attr['WIDTH']) : $this->imageWidth;

        // 96 DPI conversion (px to mm)
        $width = $targetPx * 25.4 / 96;

        // Constrain to printable page width
        if ($width > $maxWidth) {
            $width = $maxWidth;
        }

        // Calculate height from native aspect ratio
        if ($imageSize !== false && $imageSize[0] > 0 && $imageSize[1] > 0) {
            $nativeRatio = $imageSize[0] / $imageSize[1];
            $height = $width / $nativeRatio;
        } else {
            // Cannot read dimensions: let FPDF auto-calculate height
            $height = 0;
        }

        // Place image on its own line at left margin
        $this->Ln(5);

        // Use y=null to enable FPDF's flowing mode (automatic page breaks)
        $this->Image($src, $this->lMargin, null, $width, $height);

        // Advance past the image
        $this->Ln(5);
        $this->SetX($this->lMargin);
    }

    public function freeMemory()
    {
        $this->buffer = '';
        $this->pages = [];
        $this->images = [];
        $this->PageInfo = [];
    }

    protected function resolveImageSrc($url)
    {
        if (empty($this->uploadBaseDir)) {
            return $url;
        }
        $local = str_replace(
            [$this->uploadBaseUrlHttps, $this->uploadBaseUrl],
            $this->uploadBaseDir,
            $url
        );
        if ($local === $url || !file_exists($local)) {
            return $url;
        }
        return $this->upgradeToBestQuality($local);
    }

    protected function upgradeToBestQuality($path)
    {
        // Max file size to keep memory safe (5 MB)
        $maxSize = 5 * 1024 * 1024;

        if (!preg_match('/-\d+x\d+(\.\w+)$/', $path)) {
            return $path;
        }

        $original = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $path);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $scaled = preg_replace('/\.' . preg_quote($ext, '/') . '$/', '-scaled.' . $ext, $original);

        if (file_exists($scaled) && filesize($scaled) <= $maxSize) {
            return $scaled;
        }

        if (file_exists($original) && filesize($original) <= $maxSize) {
            return $original;
        }

        return $path;
    }
}
