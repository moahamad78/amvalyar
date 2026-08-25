<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use InvalidArgumentException;
use Morilog\Jalali\Jalalian;
use Throwable;

final class JalaliDate
{
    /*
     |--------------------------------------------------------------------------
     | Display
     |--------------------------------------------------------------------------
     */

    public static function date(
        DateTimeInterface|CarbonInterface|string|null $value,
        string $empty = '-'
    ): string {
        if (
            $value === null
            || $value === ''
        ) {
            return $empty;
        }

        try {

            $carbon =
                self::toCarbon($value);

            return Jalalian::fromCarbon(
                $carbon
            )->format(
                'Y/m/d'
            );

        } catch (Throwable) {

            return $empty;
        }
    }


    public static function dateTime(
        DateTimeInterface|CarbonInterface|string|null $value,
        string $empty = '-'
    ): string {
        if (
            $value === null
            || $value === ''
        ) {
            return $empty;
        }

        try {

            $carbon =
                self::toCarbon($value);

            return Jalalian::fromCarbon(
                $carbon
            )->format(
                'Y/m/d H:i'
            );

        } catch (Throwable) {

            return $empty;
        }
    }


    /*
     |--------------------------------------------------------------------------
     | Jalali -> Gregorian
     |--------------------------------------------------------------------------
     */

    public static function toGregorianDate(
        ?string $jalaliDate
    ): ?string {
        $jalaliDate =
            self::normalizeInput(
                $jalaliDate
            );

        if (
            $jalaliDate === null
            || $jalaliDate === ''
        ) {
            return null;
        }


        /*
         * 1405-05-16
         * نیز قبول می‌شود.
         */
        $jalaliDate =
            str_replace(
                '-',
                '/',
                $jalaliDate
            );


        if (
            preg_match(
                '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/',
                $jalaliDate,
                $matches
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'فرمت تاریخ شمسی معتبر نیست. نمونه صحیح: 1405/05/16'
            );
        }


        $year =
            (int) $matches[1];

        $month =
            (int) $matches[2];

        $day =
            (int) $matches[3];


        /*
         * خود Jalalian اعتبار واقعی تاریخ
         * مثل تعداد روزهای ماه را بررسی می‌کند.
         */
        $jalali =
            new Jalalian(
                $year,
                $month,
                $day
            );


        return $jalali
            ->toCarbon()
            ->format(
                'Y-m-d'
            );
    }


    /*
     |--------------------------------------------------------------------------
     | Gregorian -> Jalali input value
     |--------------------------------------------------------------------------
     */

    public static function input(
        DateTimeInterface|CarbonInterface|string|null $value
    ): string {
        if (
            $value === null
            || $value === ''
        ) {
            return '';
        }

        return self::date(
            $value,
            ''
        );
    }


    /*
     |--------------------------------------------------------------------------
     | Persian / Arabic digits
     |--------------------------------------------------------------------------
     */

    public static function englishDigits(
        string $value
    ): string {
        return strtr(
            $value,
            [
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',

                '٠' => '0',
                '١' => '1',
                '٢' => '2',
                '٣' => '3',
                '٤' => '4',
                '٥' => '5',
                '٦' => '6',
                '٧' => '7',
                '٨' => '8',
                '٩' => '9',
            ]
        );
    }


    /*
     |--------------------------------------------------------------------------
     | Internal
     |--------------------------------------------------------------------------
     */

    private static function normalizeInput(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                self::englishDigits(
                    $value
                )
            );

        return $value === ''
            ? null
            : $value;
    }


    private static function toCarbon(
        DateTimeInterface|CarbonInterface|string $value
    ): Carbon {
        if (
            $value instanceof CarbonInterface
        ) {
            return Carbon::instance(
                $value
            );
        }


        if (
            $value instanceof DateTimeInterface
        ) {
            return Carbon::instance(
                $value
            );
        }


        return Carbon::parse(
            $value
        );
    }
}