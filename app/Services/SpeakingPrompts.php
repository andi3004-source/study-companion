<?php

namespace App\Services;

/** Template-based prompt generators (spec section 10). Pure functions, no external calls. */
class SpeakingPrompts
{
    public static function part1(string $topic): array
    {
        return [
            "Do you often think about {$topic}? Why or why not?",
            "How has {$topic} changed in your country in recent years?",
            "What do you like most about {$topic}?",
        ];
    }

    public static function part3(string $topic): array
    {
        return [
            "What are the advantages and disadvantages of {$topic} today?",
            "How might {$topic} change in the next 10 years?",
            "Do you think {$topic} is more important in cities or in rural areas? Why?",
        ];
    }

    public static function part2(string $topic): array
    {
        return [
            "Describe an experience related to {$topic}.\nYou should say:\n- what happened\n- who was involved\n- why it was memorable\nand explain how you felt about it.",
        ];
    }

    public static function generate(string $part, ?string $topic): array
    {
        $topic ??= 'this topic';

        return match (true) {
            str_contains($part, '2') => self::part2($topic),
            str_contains($part, '3') => self::part3($topic),
            default => self::part1($topic),
        };
    }

    public static function writingPrompt(?string $topic): string
    {
        $topic ??= 'this issue';

        return "Some people believe that {$topic} greatly affects modern life, while others disagree. Discuss both views and give your own opinion.";
    }
}
