<?php

namespace App\Services\Ai;

/**
 * Ovozdan matnga aylantirilgan buyruqni (o'zbek lotin / rus) tuzilmaga aylantiradi:
 * niyat (intent), summa/miqdor va ism. Ma'lumotlar bazasiga tegmaydi (sof matn tahlili).
 *
 * Niyatlar: debt_add, debt_payment, stock_in, show_sales, show_profit,
 * show_debts, show_low_stock, unknown.
 */
class VoiceCommandParser
{
    public const DEBT_ADD = 'debt_add';

    public const DEBT_PAYMENT = 'debt_payment';

    public const STOCK_IN = 'stock_in';

    public const SHOW_SALES = 'show_sales';

    public const SHOW_PROFIT = 'show_profit';

    public const SHOW_DEBTS = 'show_debts';

    public const SHOW_LOW_STOCK = 'show_low_stock';

    public const UNKNOWN = 'unknown';

    /** @var array<string, int> */
    private const UNITS = [
        // o'zbek
        'nol' => 0, 'bir' => 1, 'ikki' => 2, 'uch' => 3, "to'rt" => 4, 'tort' => 4, 'besh' => 5, 'olti' => 6,
        'yetti' => 7, 'sakkiz' => 8, "to'qqiz" => 9, 'toqqiz' => 9, "o'n" => 10, 'on' => 10,
        'yigirma' => 20, "o'ttiz" => 30, 'ottiz' => 30, 'qirq' => 40, 'ellik' => 50, 'oltmish' => 60,
        'yetmish' => 70, 'sakson' => 80, "to'qson" => 90, 'toqson' => 90,
        // rus
        'один' => 1, 'одна' => 1, 'два' => 2, 'две' => 2, 'три' => 3, 'четыре' => 4, 'пять' => 5, 'шесть' => 6,
        'семь' => 7, 'восемь' => 8, 'девять' => 9, 'десять' => 10, 'двадцать' => 20, 'тридцать' => 30,
        'сорок' => 40, 'пятьдесят' => 50, 'шестьдесят' => 60, 'семьдесят' => 70, 'восемьдесят' => 80,
        'девяносто' => 90, 'двести' => 200, 'триста' => 300, 'четыреста' => 400, 'пятьсот' => 500,
        'шестьсот' => 600, 'семьсот' => 700, 'восемьсот' => 800, 'девятьсот' => 900,
    ];

    private const HUNDRED = ['yuz', 'сто'];

    private const THOUSAND = ['ming', 'тысяча', 'тысячи', 'тысяч', 'тыс'];

    private const MILLION = ['million', 'mln', 'миллион', 'миллиона', 'миллионов', 'млн'];

    /** Ism/mahsulot nomidan oldin kesib tashlanadigan so'zlar */
    private const STOP = [
        'aka', 'akaga', 'akadan', 'akaning', 'opa', 'opaga', 'opadan', 'amaki', 'amakiga', 'xola', 'xolaga',
        'ustoz', 'ustozga', 'mijoz', 'mijozga', 'ga', 'dan', 'ning', 'bugun', 'bugungi',
        'qarz', 'qarzga', 'qarzini', 'qarzi', 'yoz', 'yozib', 'yozing', 'yozdir', "qo'y", 'qoy', "qo'ying", 'qoying',
        'bering', 'kiriting', 'kiritib', 'qiling', 'qilib', "qo'sh", 'qosh', 'berdi', 'qaytardi', "to'ladi",
        'toladi', 'kirim', 'qil', 'omborga', 'tovar', 'mahsulot', 'dona', 'ta', 'kg', 'metr', 'litr', "so'm", 'som',
        'долг', 'долга', 'запиши', 'запись', 'вернул', 'вернула', 'отдал', 'отдала', 'погасил', 'оплатил',
        'приход', 'оприходуй', 'штук', 'шт', 'сум', 'добавь', 'на', 'склад', 'товар', 'у', 'от', 'для',
    ];

    /** @return array{intent: string, text: string, amount: ?float, quantity: ?float, name: ?string, product: ?string} */
    public function parse(string $raw): array
    {
        $text = $this->normalize($raw);

        // Ovoz tanish xizmati o'zbek gapini kirill harflarda qaytarishi mumkin ("Али акага 150 минг қарз ёз"):
        // o'zbekcha belgilar bor yoki rus tilida buyruq topilmasa — lotinga o'tkazib qayta tahlil qilamiz.
        if (preg_match('/[\x{0400}-\x{04FF}]/u', $text)
            && (preg_match('/[ўқғҳ]/u', $text) || $this->intent($text) === self::UNKNOWN)) {
            $latin = $this->normalize($this->transliterate($text));

            if ($this->intent($latin) !== self::UNKNOWN) {
                $text = $latin;
            }
        }

        $tokens = $text === '' ? [] : preg_split('/\s+/u', $text);

        [$number, $numberStart] = $this->firstNumber($tokens);
        $intent = $this->intent($text);

        $name = null;
        $product = null;

        if (in_array($intent, [self::DEBT_ADD, self::DEBT_PAYMENT], true)) {
            $name = $this->leadingName($tokens, $numberStart);
        }

        if ($intent === self::STOCK_IN) {
            $product = $this->leadingName($tokens, $numberStart);
        }

        return [
            'intent' => $intent,
            'text' => $text,
            'amount' => in_array($intent, [self::DEBT_ADD, self::DEBT_PAYMENT], true) ? $number : null,
            'quantity' => $intent === self::STOCK_IN ? $number : null,
            'name' => $name,
            'product' => $product,
        ];
    }

    /** Kichik harf, apostrof variantlarini birlashtirish, belgilarni olib tashlash */
    public function normalize(string $raw): string
    {
        $text = mb_strtolower(trim($raw));
        $text = str_replace(['’', 'ʻ', 'ʼ', '‘', '`', '´', '′'], "'", $text);
        $text = preg_replace("/[^\p{L}\p{N}'\s.,]/u", ' ', $text);
        $text = preg_replace('/(?<=\d)[.,](?=\d{3}\b)/', '', $text); // 150,000 -> 150000
        $text = str_replace(',', '.', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Kirill ismni lotinga (mijoz/mahsulot nomlari bilan solishtirish uchun) */
    public function transliterate(string $text): string
    {
        $map = ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'j',
            'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'x', 'ц' => 'ts',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'i', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
            'я' => 'ya', 'ў' => "o'", 'қ' => 'q', 'ғ' => "g'", 'ҳ' => 'h'];

        return strtr(mb_strtolower($text), $map);
    }

    private function intent(string $t): string
    {
        $has = fn (string $pattern): bool => (bool) preg_match($pattern.'u', $t);

        // O'qish so'rovlari avval (ular raqam/ism talab qilmaydi)
        if ($has("/(kam qol|tugab|tugagan|qoldiq|заканчива|мало на складе|остатк)/")) {
            return self::SHOW_LOW_STOCK;
        }

        if ($has("/(muddati o'tgan|qarzdor|kimlarning qarzi|kim qarz|должник|просроченн|у кого долг)/")) {
            return self::SHOW_DEBTS;
        }

        if ($has('/(foyda|прибыл)/')) {
            return self::SHOW_PROFIT;
        }

        // Qarz to'lovi (qarz yozishdan oldin: "qarzini berdi" ham "qarz" so'zini o'z ichiga oladi)
        if ($has("/(qarzini (berdi|qaytardi|to'ladi|toladi)|qarzidan|qaytardi|qaytarib|to'ladi|toladi|to'lab|tolab|berdi|to'lov oldim|to'lov qildi|вернул|отдал|погасил|оплатил)/")
            && ! $has('/(kirim|приход)/')) {
            return self::DEBT_PAYMENT;
        }

        if ($has('/(kirim|omborga|ombor ga|оприходуй|приход|добавь на склад)/')) {
            return self::STOCK_IN;
        }

        if ($has('/(qarz|долг|запиши)/')) {
            return self::DEBT_ADD;
        }

        if ($has('/(savdo|sotuv|sotildi|продаж|выручк|торговл)/')) {
            return self::SHOW_SALES;
        }

        return self::UNKNOWN;
    }

    /**
     * Matndagi birinchi son (raqam yoki so'z bilan: "yuz ellik ming", "150 ming", "1.5 million").
     *
     * @param  list<string>  $tokens
     * @return array{0: ?float, 1: ?int} [qiymat, boshlanish indeksi]
     */
    private function firstNumber(array $tokens): array
    {
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! $this->isNumberToken($tokens[$i])) {
                continue;
            }

            $total = 0.0;
            $current = 0.0;
            $prevDigit = false;

            for ($j = $i; $j < $count && $this->isNumberToken($tokens[$j]); $j++) {
                $t = $tokens[$j];

                if (is_numeric($t)) {
                    $v = (float) $t;
                    // "150 000": ketma-ket 3 xonali guruh mingliklarni bildiradi
                    if ($prevDigit && preg_match('/^\d{3}$/', $t) && $current >= 1) {
                        $current = $current * 1000 + $v;
                    } else {
                        $current += $v;
                    }
                    $prevDigit = true;

                    continue;
                }

                $prevDigit = false;

                if (isset(self::UNITS[$t])) {
                    $current += self::UNITS[$t];
                } elseif (in_array($t, self::HUNDRED, true)) {
                    $current = ($current == 0 ? 1 : $current) * 100;
                } elseif (in_array($t, self::THOUSAND, true)) {
                    $total += ($current == 0 ? 1 : $current) * 1000;
                    $current = 0.0;
                } elseif (in_array($t, self::MILLION, true)) {
                    $total += ($current == 0 ? 1 : $current) * 1_000_000;
                    $current = 0.0;
                }
            }

            $value = $total + $current;

            return $value > 0 ? [$value, $i] : [null, null];
        }

        return [null, null];
    }

    private function isNumberToken(string $t): bool
    {
        return is_numeric($t)
            || isset(self::UNITS[$t])
            || in_array($t, [...self::HUNDRED, ...self::THOUSAND, ...self::MILLION], true);
    }

    /**
     * Ism yoki mahsulot nomi: son/kalit so'zgacha bo'lgan so'zlar (maks. 3), hurmat so'zlarisiz.
     *
     * @param  list<string>  $tokens
     */
    private function leadingName(array $tokens, ?int $numberStart): ?string
    {
        $limit = $numberStart ?? count($tokens);
        $parts = [];

        for ($i = 0; $i < $limit; $i++) {
            $t = $tokens[$i];

            if (in_array($t, self::STOP, true) || $this->isNumberToken($t)) {
                // Ism kalit so'zdan boshlanib ketsa ("qarz Ali ga 100 ming") — kalit so'zni o'tkazib yuboramiz
                if ($parts === []) {
                    continue;
                }

                break;
            }

            $parts[] = $t;

            if (count($parts) === 3) {
                break;
            }
        }

        if ($parts === []) {
            // Ism sondan keyin aytilgan bo'lishi mumkin: "100 ming qarz yoz Aliga"
            for ($i = ($numberStart ?? 0); $i < count($tokens); $i++) {
                $t = $tokens[$i];
                if (! in_array($t, self::STOP, true) && ! $this->isNumberToken($t) && preg_match('/^\p{L}+$/u', $t)) {
                    $parts[] = $t;
                    break;
                }
            }
        }

        if ($parts === []) {
            return null;
        }

        // "aliga" kabi qo'shimchali shaklni tozalash (faqat natija kamida 3 harf bo'lsa)
        $last = $parts[count($parts) - 1];
        $stripped = preg_replace('/(ga|dan|ning|ni)$/u', '', $last);

        if ($stripped !== null && mb_strlen($stripped) >= 3) {
            $parts[count($parts) - 1] = $stripped;
        }

        return implode(' ', $parts);
    }
}
