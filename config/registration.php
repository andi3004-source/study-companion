<?php

/**
 * Who may create an account (resources/views/livewire/pages/auth/register.blade.php).
 * enabled=false closes registration: the page shows a friendly message and the
 * "Daftar" links are hidden. A non-empty invite_code makes the form ask for it;
 * it is compared with hash_equals and never rendered.
 */
return [
    'enabled' => (bool) env('REGISTRATION_ENABLED', true),

    'invite_code' => (string) env('REGISTRATION_INVITE_CODE', ''),

    'closed_message' => 'Pendaftaran ditutup. Gunakan akun demo atau hubungi tim',
];
