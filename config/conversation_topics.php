<?php

/**
 * Topic pool for the "Ngobrol Bebas" (free chat) conversation mode.
 *
 * English reuses week_topics (the IELTS speaking curriculum's 24 topics,
 * week_number 1-8 — week 9 is "Review Semua Topik", not a real topic) via
 * the WeekTopic model, so a change there is automatically picked up here
 * too. Mandarin has no equivalent source yet (HSK1 curriculum doesn't have
 * topic-based speaking content), so its 18-topic pool is hardcoded here at
 * a simpler, HSK1-appropriate level.
 */
return [

    'mandarin' => [
        ['hanzi' => '家庭', 'pinyin' => 'jiātíng', 'meaning_id' => 'keluarga'],
        ['hanzi' => '爱好', 'pinyin' => 'àihào', 'meaning_id' => 'hobi'],
        ['hanzi' => '天气', 'pinyin' => 'tiānqì', 'meaning_id' => 'cuaca'],
        ['hanzi' => '食物', 'pinyin' => 'shíwù', 'meaning_id' => 'makanan'],
        ['hanzi' => '旅行', 'pinyin' => 'lǚxíng', 'meaning_id' => 'jalan-jalan'],
        ['hanzi' => '工作', 'pinyin' => 'gōngzuò', 'meaning_id' => 'pekerjaan'],
        ['hanzi' => '学校', 'pinyin' => 'xuéxiào', 'meaning_id' => 'sekolah'],
        ['hanzi' => '购物', 'pinyin' => 'gòuwù', 'meaning_id' => 'belanja'],
        ['hanzi' => '运动', 'pinyin' => 'yùndòng', 'meaning_id' => 'olahraga'],
        ['hanzi' => '电影和音乐', 'pinyin' => 'diànyǐng hé yīnyuè', 'meaning_id' => 'film & musik'],
        ['hanzi' => '朋友', 'pinyin' => 'péngyou', 'meaning_id' => 'teman'],
        ['hanzi' => '假期', 'pinyin' => 'jiàqī', 'meaning_id' => 'liburan'],
        ['hanzi' => '健康', 'pinyin' => 'jiànkāng', 'meaning_id' => 'kesehatan'],
        ['hanzi' => '宠物', 'pinyin' => 'chǒngwù', 'meaning_id' => 'hewan peliharaan'],
        ['hanzi' => '交通', 'pinyin' => 'jiāotōng', 'meaning_id' => 'transportasi'],
        ['hanzi' => '每天的生活', 'pinyin' => 'měitiān de shēnghuó', 'meaning_id' => 'kegiatan sehari-hari'],
        ['hanzi' => '未来计划', 'pinyin' => 'wèilái jìhuà', 'meaning_id' => 'rencana masa depan'],
        ['hanzi' => '节日', 'pinyin' => 'jiérì', 'meaning_id' => 'hari raya'],
    ],

];
