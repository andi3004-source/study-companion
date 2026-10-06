<?php

namespace App\Enums;

/**
 * Fixed categories for the "Kesalahan terbanyakmu" analysis of Mandarin
 * conversation sessions. The single source for the analyzer prompts (the
 * allowed values), the stored data (error_analysis.items[].kategori) and
 * every place that shows a category to the learner (label()).
 */
enum ErrorCategory: string
{
    case UrutanKata = 'urutan_kata';
    case ShiDenganKataSifat = 'shi_dengan_kata_sifat';
    case BuAtauMei = 'bu_atau_mei';
    case KataPenggolong = 'kata_penggolong';
    case Nada = 'nada';
    case Pelafalan = 'pelafalan';
    case Kosakata = 'kosakata';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::UrutanKata => 'Urutan kata',
            self::ShiDenganKataSifat => '是 dengan kata sifat',
            self::BuAtauMei => '不 atau 没',
            self::KataPenggolong => 'Kata penggolong',
            self::Nada => 'Nada',
            self::Pelafalan => 'Pelafalan',
            self::Kosakata => 'Pilihan kata',
            self::Lainnya => 'Lainnya',
        };
    }

    /** Anything the model invents outside the fixed list (or leaves empty) counts as 'lainnya'. */
    public static function fromLoose(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom(strtolower(trim($value))) ?? self::Lainnya) : self::Lainnya;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
