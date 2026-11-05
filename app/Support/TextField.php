<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Character rules shared by every MediByte form.
 *
 * The browser strips keys that do not belong while the user types, and these
 * patterns are the authority behind that: an API client posting raw JSON is
 * held to the same rules as the UI. Free text — notes, diary reflections,
 * appointment reasons and document titles — is deliberately absent here,
 * because those fields are meant to hold anything the user writes.
 */
final class TextField
{
    /**
     * Proper nouns: letters, spaces and the punctuation a name legitimately
     * carries (Dr., St. Luke's, Smith-Jones). Digits and other symbols fail.
     */
    public const NAME = '/^[\p{L}\p{M}]+(?:[\p{L}\p{M}\s\'’.\-]*[\p{L}\p{M}]+)?$/u';

    /**
     * Labels that genuinely do carry numerals — Vitamin B12, Metformin ER 500,
     * Chicken 65 — while still rejecting markup and stray symbols. The first
     * character has to be a letter: these fields are names of things, and
     * "2 bottles of water" belongs in the notes, not in a label.
     */
    public const LABEL = '/^[\p{L}][\p{L}\p{N}\p{M}\s\'’.,:()\/+&%\-]*$/u';

    /** A measurement leads with the number: 500 mg, 2 tablets, 1x daily. */
    public const MEASURE = '/^[\p{L}\p{N}][\p{L}\p{N}\p{M}\s\'’.,:()\/+&%\-]*$/u';

    /** Dialable characters only: 0917 123 4567, +63 917-123-4567. */
    public const PHONE = '/^\+?[\p{N}][\p{N}\s().\-]*$/u';

    /** Street addresses mix letters and numerals with , . # / and ( ). */
    public const ADDRESS = '/^[\p{L}\p{N}][\p{L}\p{N}\s.,#\'&\/()\-.]*$/u';

    /**
     * @var array<string, array{pattern: string, case: bool, message: string}>
     */
    private const KINDS = [
        'name' => [
            'pattern' => self::NAME,
            'case' => true,
            'message' => 'Use letters only, with spaces, apostrophes, hyphens or dots.',
        ],
        'label' => [
            'pattern' => self::LABEL,
            'case' => true,
            'message' => 'Use letters, numbers and simple punctuation only.',
        ],
        // Same characters as a label, but a dose leads with its number
        // and "500 mg" must stay lower-case.
        'measure' => [
            'pattern' => self::MEASURE,
            'case' => false,
            'message' => 'Use letters, numbers and simple punctuation only.',
        ],
        'phone' => [
            'pattern' => self::PHONE,
            'case' => false,
            'message' => 'Use digits, and the characters + ( ) - . and spaces.',
        ],
        'address' => [
            'pattern' => self::ADDRESS,
            'case' => false,
            'message' => 'Use letters, numbers and address punctuation only.',
        ],
    ];

    /**
     * Validation rules for one field, ready to drop into a rules array.
     *
     * @return array<int, string>
     */
    public static function rules(string $kind, string $presence = 'nullable', int $max = 150): array
    {
        return [$presence, 'string', 'max:'.$max, 'regex:'.self::KINDS[$kind]['pattern']];
    }

    /**
     * @param  array<string, string>  $fields  field name => kind
     * @return array<string, string>
     */
    public static function messages(array $fields): array
    {
        $messages = [];

        foreach ($fields as $field => $kind) {
            $messages[$field.'.regex'] = self::KINDS[$kind]['message'];
        }

        return $messages;
    }

    /**
     * Squash repeated spaces and title-case the kinds that are names, so what
     * the user stores matches what the form shows them. Array fields, like the
     * list of things that affected a mood, get cleaned item by item.
     *
     * @param  array<string, string>  $fields  field name => kind
     */
    public static function normalize(Request $request, array $fields): void
    {
        $clean = [];

        foreach ($fields as $field => $kind) {
            $value = $request->input($field);

            if (is_array($value)) {
                $clean[$field] = array_map(
                    fn ($item) => is_string($item) ? self::cast($item, $kind) : $item,
                    $value
                );

                continue;
            }

            if (is_string($value)) {
                $clean[$field] = self::cast($value, $kind);
            }
        }

        if ($clean !== []) {
            $request->merge($clean);
        }
    }

    private static function cast(string $value, string $kind): string
    {
        $squashed = self::squash($value);

        return self::KINDS[$kind]['case'] ? self::titleCase($squashed) : $squashed;
    }

    public static function squash(string $value): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim($value));
    }

    public static function titleCase(string $value): string
    {
        return mb_convert_case(mb_strtolower(self::squash($value), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}
