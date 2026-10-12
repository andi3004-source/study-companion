<?php

namespace App\Livewire\Games;

use App\Support\ErrorGameItems;
use App\Support\ListenPickWords;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Game hub: one card per game. Add a game by adding an entry to games();
 * 'available' decides whether the card links to the game or explains why
 * there is nothing to play yet.
 */
#[Layout('layouts.study')]
class Hub extends Component
{
    /** @return array<int, array{judul: string, penjelasan: string, status: string, cara: string, route: string, tautan: string, available: bool, kosong: string}> */
    public function games(): array
    {
        return [
            [
                'judul' => 'Dengar & Pilih',
                'penjelasan' => 'Dengarkan satu kata Mandarin, baca hanzi dan pinyinnya, lalu pilih artinya dalam bahasa Indonesia.',
                'status' => 'Draf',
                'cara' => 'Cara bermain: keyboard dan suara, tanpa batas waktu.',
                'route' => 'game.listen-pick',
                'tautan' => 'Mulai Dengar & Pilih',
                'available' => ListenPickWords::enough(),
                'kosong' => 'Belum ada cukup kosakata HSK 1 yang punya arti bahasa Indonesia untuk membuat soal. Isi data kosakata dulu, lalu buka halaman ini lagi.',
            ],
            [
                'judul' => 'Pemburu Kesalahan',
                'penjelasan' => 'Baca kalimat Mandarin yang keliru, lalu tentukan jenis kesalahannya.',
                'status' => 'Draf',
                'cara' => 'Cara bermain: keyboard dan suara, tanpa batas waktu.',
                'route' => 'game.error-hunter',
                'tautan' => 'Mulai Pemburu Kesalahan',
                'available' => ErrorGameItems::all() !== [],
                'kosong' => 'Soal Pemburu Kesalahan belum tersedia karena berkas soalnya belum dipasang. Sementara itu, coba game lain atau latihan di halaman Kartu.',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.games.hub', ['games' => $this->games()])->title('Game');
    }
}
