<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_plate_templates')) {
            return;
        }

        DB::table('asset_plate_templates')
            ->orderBy('id')
            ->get()
            ->each(
                function (object $row): void {
                    $elements =
                        json_decode(
                            (string) $row->elements,
                            true
                        );

                    if (!is_array($elements)) {
                        return;
                    }

                    $seenAssetCode =
                        false;

                    $normalized =
                        [];

                    foreach ($elements as $element) {
                        if (!is_array($element)) {
                            continue;
                        }

                        $field =
                            (string) (
                                $element['field']
                                ?? ''
                            );

                        if ($field === 'plate_number') {
                            $field = 'asset_code';
                            $element['field'] = 'asset_code';
                            $element['label'] = 'کد اموال';
                        }

                        if ($field === 'asset_code') {
                            if ($seenAssetCode) {
                                continue;
                            }

                            $seenAssetCode = true;
                        }

                        $normalized[] =
                            $element;
                    }

                    DB::table('asset_plate_templates')
                        ->where('id', $row->id)
                        ->update([
                            'elements' =>
                                json_encode(
                                    $normalized,
                                    JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                                ),
                        ]);
                }
            );
    }

    public function down(): void
    {
        /*
         * Deliberately irreversible at the semantic level:
         * plate_number was a duplicate identifier and is no longer
         * restored as an independent business concept.
         */
    }
};
