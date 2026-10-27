<?php

namespace App\Models\Labels\Custom;

use App\Helpers\Helper;
use App\Models\Labels\RectangleSheet;
use App\Models\Setting;
use TCPDF;

/**
 * Custom dense sheet label: QR top-left, logo top-right,
 * title + N fields beside QR, remaining fields full-width under QR.
 *
 * Tunables are in millimetres / points at the top of the class.
 * Page/label geometry still comes from Snipe Admin → Labels settings
 * (same as DefaultLabel).
 *
 * UI name after deploy: Custom\DenseQrSheet
 * Path on server: app/Models/Labels/Custom/DenseQrSheet.php
 */
class DenseQrSheet extends RectangleSheet
{
    // --- Tunables (mm / pt) -----------------------------------------------
    protected static float $barcode1dSize_mm = 3.8;
    protected static float $barcode2dSize_mm = 17;
    protected static float $barcode2dMargin_mm = 1.8;
    protected static array $logoSize_mm = [19.05, 12.8];
    protected static float $logoMargin_mm = 1.28;
    protected static float $textMargin_mm = 1.02;

    protected static float $titleFontSize_pt = 10;
    protected static float $fieldFontSize_pt = 8;

    /** How many non-empty fields render to the right of the QR. */
    protected static int $fieldsInRightColumn = 4;

    // --- Runtime (inches) -------------------------------------------------
    private float $barcode1dSize;
    private float $barcode2dSize;
    private float $barcode2dMargin;
    private array $logoSize;
    private float $logoMargin;
    private float $textMargin;
    private float $titleTextSize;
    private float $fieldTextSize;

    private float $labelWidth;
    private float $labelHeight;
    private float $labelSpacingH;
    private float $labelSpacingV;
    private float $pageMarginTop;
    private float $pageMarginBottom;
    private float $pageMarginLeft;
    private float $pageMarginRight;
    private float $pageWidth;
    private float $pageHeight;
    private int $columns;
    private int $rows;

    public function __construct()
    {
        $this->barcode1dSize = Helper::convertUnit(self::$barcode1dSize_mm, 'mm', 'in');
        $this->barcode2dSize = Helper::convertUnit(self::$barcode2dSize_mm, 'mm', 'in');
        $this->barcode2dMargin = Helper::convertUnit(self::$barcode2dMargin_mm, 'mm', 'in');
        $this->logoSize = [
            Helper::convertUnit(self::$logoSize_mm[0], 'mm', 'in'),
            Helper::convertUnit(self::$logoSize_mm[1], 'mm', 'in'),
        ];
        $this->logoMargin = Helper::convertUnit(self::$logoMargin_mm, 'mm', 'in');
        $this->textMargin = Helper::convertUnit(self::$textMargin_mm, 'mm', 'in');
        $this->titleTextSize = Helper::convertUnit(self::$titleFontSize_pt, 'pt', 'in');
        $this->fieldTextSize = Helper::convertUnit(self::$fieldFontSize_pt, 'pt', 'in');

        $settings = Setting::getSettings();

        $this->labelWidth = $this->setLabelWidth($settings);
        $this->labelHeight = $this->setLabelHeight($settings);

        $this->labelSpacingH = $settings->labels_display_sgutter;
        $this->labelSpacingV = $settings->labels_display_bgutter;

        $this->pageMarginTop = $settings->labels_pmargin_top;
        $this->pageMarginBottom = $settings->labels_pmargin_bottom;
        $this->pageMarginLeft = $settings->labels_pmargin_left;
        $this->pageMarginRight = $settings->labels_pmargin_right;

        $this->pageWidth = $settings->labels_pagewidth;
        $this->pageHeight = $settings->labels_pageheight;

        $usableWidth = $this->pageWidth - $this->pageMarginLeft - $this->pageMarginRight;
        $usableHeight = $this->pageHeight - $this->pageMarginTop - $this->pageMarginBottom;

        $this->columns = $this->calculateGridCount($usableWidth, $this->labelWidth, $this->labelSpacingH);
        $this->rows = $this->calculateGridCount($usableHeight, $this->labelHeight, $this->labelSpacingV);
    }

    public function getUnit()
    {
        return 'in';
    }

    public function getPageWidth()
    {
        return $this->pageWidth;
    }

    public function getPageHeight()
    {
        return $this->pageHeight;
    }

    public function getPageMarginTop()
    {
        return $this->pageMarginTop;
    }

    public function getPageMarginBottom()
    {
        return $this->pageMarginBottom;
    }

    public function getPageMarginLeft()
    {
        return $this->pageMarginLeft;
    }

    public function getPageMarginRight()
    {
        return $this->pageMarginRight;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getRows()
    {
        return $this->rows;
    }

    public function getLabelBorder()
    {
        return 0;
    }

    public function getLabelWidth()
    {
        return $this->labelWidth;
    }

    public function getLabelHeight()
    {
        return $this->labelHeight;
    }

    public function getLabelMarginTop()
    {
        return 0;
    }

    public function getLabelMarginBottom()
    {
        return 0;
    }

    public function getLabelMarginLeft()
    {
       return 0;
    }

    public function getLabelMarginRight()
    {
        return 0;
    }

    public function getLabelColumnSpacing()
    {
        return $this->labelSpacingH;
    }

    public function getLabelRowSpacing()
    {
        return $this->labelSpacingV;
    }

    public function getSupportAssetTag()
    {
        return false;
    }

    public function getSupport1DBarcode()
    {
        return true;
    }

    public function getSupport2DBarcode()
    {
        return true;
    }

    public function getSupportFields()
    {
        return 6;
    }

    public function getSupportTitle()
    {
        return true;
    }

    public function getSupportLogo()
    {
        return true;
    }

    public function preparePDF(TCPDF $pdf): void {}

    public function write($pdf, $record)
    {
        $textY = 0;
        $textX1 = 0;
        $textX2 = $this->getLabelWidth();

        if ($record->get('barcode1d')) {
            static::write1DBarcode(
                $pdf, $record->get('barcode1d')->content, $record->get('barcode1d')->type,
                0.05, $this->getLabelHeight() - $this->barcode1dSize,
                $this->getLabelWidth() - 0.1, $this->barcode1dSize
            );
        }

        if ($record->get('barcode2d')) {
            static::write2DBarcode(
                $pdf, $record->get('barcode2d')->content, $record->get('barcode2d')->type,
                0, 0, $this->barcode2dSize, $this->barcode2dSize
            );
            $textX1 += $this->barcode2dSize + $this->barcode2dMargin;
        }

        if ($record->get('logo')) {
            $logoSize = static::writeImage(
                $pdf, $record->get('logo'),
                $this->labelWidth - $this->logoSize[0], 0,
                $this->logoSize[0], $this->logoSize[1],
                'R', 'T', 300, true, false, 0
            );
            $textX2 -= ($logoSize[0] + $this->logoMargin);
        }

        $textW = $textX2 - $textX1;

        if ($record->get('title')) {
            static::writeText(
                $pdf, $record->get('title'),
                $textX1, 0,
                Helper::isCjk($record->get('title')) ? 'cid0cs' : 'freesans', 'b', $this->titleTextSize, 'L',
                $textW, $this->titleTextSize,
                true, 0
            );
            $textY += $this->titleTextSize + $this->textMargin;
        }

        $fields = collect($record->get('fields') ?? [])->values()->all();
        $nonEmptyFields = [];
        foreach ($fields as $field) {
            $value = is_array($field) ? ($field['value'] ?? '') : '';
            if ($value !== '' && $value !== null) {
                $nonEmptyFields[] = $field;
            }
        }

        $nonEmptyFields = array_slice($nonEmptyFields, 0, $this->getSupportFields());
        $totalNonEmpty = count($nonEmptyFields);
        $fieldsInRightColumn = min(self::$fieldsInRightColumn, $totalNonEmpty);

        $printedRight = 0;
        for ($i = 0; $i < $fieldsInRightColumn; $i++) {
            $field = $nonEmptyFields[$i];
            $label = $field['label'] ?? '';
            $line = ($label !== '' ? $label.' ' : '').$field['value'];
            static::writeText(
                $pdf, $line,
                $textX1, $textY,
                Helper::isCjk($label !== '' ? $label : $field['value']) ? 'cid0cs' : 'freesans', '', $this->fieldTextSize, 'L',
                $textW, $this->fieldTextSize,
                true, 0
            );
            $textY += $this->fieldTextSize + $this->textMargin;
            $printedRight++;
        }

        if ($totalNonEmpty > $printedRight) {
            $textY = max($textY, $this->barcode2dSize + $this->barcode2dMargin);
            $fullWidth = $this->getLabelWidth();

            for ($i = $printedRight; $i < $totalNonEmpty; $i++) {
                $field = $nonEmptyFields[$i];
                $label = $field['label'] ?? '';
                $line = ($label !== '' ? $label.' ' : '').$field['value'];
                static::writeText(
                    $pdf, $line,
                    0, $textY,
                    Helper::isCjk($label !== '' ? $label : $field['value']) ? 'cid0cs' : 'freesans', '', $this->fieldTextSize, 'L',
                    $fullWidth, $this->fieldTextSize,
                    true, 0
                );
                $textY += $this->fieldTextSize + $this->textMargin;
            }
        }
    }

    private function setLabelWidth(Setting $settings)
    {
        $labelWidth = $settings->labels_width;

        if ($labelWidth == 0) {
            $labelWidth = 0.1;
        }

        return $labelWidth;
    }

    private function setLabelHeight(?Setting $settings)
    {
        $labelHeight = $settings->labels_height;

        if ($labelHeight == 0) {
            $labelHeight = 0.1;
        }

        return $labelHeight;
    }

}
