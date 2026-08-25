<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetPlateTemplate;
use App\Models\Company;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Picqer\Barcode\BarcodeGeneratorSVG;
use RuntimeException;

final class AssetPlatePrintRenderer
{
    public function render(Asset $asset, AssetPlateTemplate $template): array
    {
        $assetCode = trim((string) ($asset->asset_code ?? ''));

        if ($assetCode === '') {
            throw new RuntimeException(
                'Asset must have a permanent asset code before plate printing.'
            );
        }

        if ((int) $asset->company_id !== (int) $template->company_id) {
            throw new RuntimeException(
                'Asset and plate template must belong to the same company.'
            );
        }

        $elements = collect($template->elements ?? [])
            ->map(fn (array $element): array => $this->renderElement($asset, $element))
            ->values()
            ->all();

        return [
            'asset_id' => (int) $asset->id,
            'asset_code' => $assetCode,
            'title' => (string) $asset->title,
            'width_mm' => (float) $template->width_mm,
            'height_mm' => (float) $template->height_mm,
            'orientation' => (string) $template->orientation,
            'elements' => $elements,
        ];
    }

    private function renderElement(Asset $asset, array $element): array
    {
        $type = (string) ($element['type'] ?? 'field');
        $field = (string) ($element['field'] ?? '');

        $value = $type === 'text'
            ? (string) ($element['text'] ?? '')
            : $this->fieldValue($asset, $field);

        $result = [
            'id' => (string) ($element['id'] ?? ''),
            'type' => $type,
            'field' => $field,
            'label' => (string) ($element['label'] ?? ''),
            'value' => $value,
            'x' => (float) ($element['x'] ?? 0),
            'y' => (float) ($element['y'] ?? 0),
            'w' => max(1, (float) ($element['w'] ?? 10)),
            'h' => max(1, (float) ($element['h'] ?? 5)),
            'font_size' => max(5, (float) ($element['font_size'] ?? 9)),
            'align' => in_array(
                $element['align'] ?? 'center',
                ['left', 'center', 'right'],
                true
            ) ? $element['align'] : 'center',
            'bold' => (bool) ($element['bold'] ?? false),
            'svg' => null,
        ];

        if ($type === 'qr') {
            $payload = $value !== '' ? $value : (string) $asset->asset_code;
            $result['svg'] = $this->qrSvg($payload);
        }

        if ($type === 'barcode') {
            $payload = $value !== '' ? $value : (string) $asset->asset_code;
            $result['svg'] = $this->barcodeSvg($payload);
        }

        return $result;
    }

    private function fieldValue(Asset $asset, string $field): string
    {
        return match ($field) {
            'company_name' => (string) (
                Company::withoutGlobalScopes()
                    ->whereKey($asset->company_id)
                    ->value('name')
                ?? ''
            ),
            'asset_code' => (string) ($asset->asset_code ?? ''),
            'title' => (string) ($asset->title ?? ''),
            'brand' => (string) ($asset->brand ?? ''),
            'model' => (string) ($asset->model ?? ''),
            'serial_number' => (string) ($asset->serial_number ?? ''),
            'coding_site' => (string) ($asset->codingSite?->name ?? ''),
            'current_site' => (string) ($asset->currentSite?->name ?? ''),
            'current_location' => (string) ($asset->currentLocation?->name ?? ''),
            default => '',
        };
    }

    private function qrSvg(string $payload): string
    {
        $qrCode = new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255),
        );

        return (new SvgWriter())
            ->write(
                $qrCode,
                null,
                null,
                [
                    SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
                ]
            )
            ->getString();
    }

    private function barcodeSvg(string $payload): string
    {
        $generator = new BarcodeGeneratorSVG();

        return $generator->getBarcode(
            $payload,
            $generator::TYPE_CODE_128,
            2,
            40
        );
    }
}