<?php
declare(strict_types=1);

function pdh_app_version_info(): array
{
    $buildDate = '2026-06-11';
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $buildDate) ?: new DateTimeImmutable('2026-06-11');

    return [
        'build_date' => $date->format('Y-m-d'),
        'version_code' => 'v' . $date->format('Y.m.d'),
        'display_th' => $date->format('d/m/Y'),
        'display_full_th' => $date->format('d/m/Y'),
    ];
}
