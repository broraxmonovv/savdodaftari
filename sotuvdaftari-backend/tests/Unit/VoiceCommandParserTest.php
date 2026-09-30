<?php

namespace Tests\Unit;

use App\Services\Ai\VoiceCommandParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VoiceCommandParserTest extends TestCase
{
    #[DataProvider('commands')]
    public function test_parses_commands(string $text, string $intent, ?float $amount, ?float $qty, ?string $name, ?string $product): void
    {
        $r = (new VoiceCommandParser)->parse($text);

        $this->assertSame($intent, $r['intent'], "intent: {$text}");
        $this->assertSame($amount, $r['amount'], "amount: {$text}");
        $this->assertSame($qty, $r['quantity'], "qty: {$text}");
        $this->assertSame($name, $r['name'], "name: {$text}");
        $this->assertSame($product, $r['product'], "product: {$text}");
    }

    public static function commands(): array
    {
        return [
            'qarz yozish' => ['Ali akaga 150 ming qarz yoz.', 'debt_add', 150000.0, null, 'ali', null],
            'qarz so\'z bilan' => ['Valiga yuz ellik ming so\'m qarz yoz', 'debt_add', 150000.0, null, 'vali', null],
            'qarz million' => ['Karim akaga 1.5 million qarz yozib qo\'y', 'debt_add', 1500000.0, null, 'karim', null],
            'qarz 2 yuz' => ['Sardorga ikki yuz ellik ming qarz yoz', 'debt_add', 250000.0, null, 'sardor', null],
            'qarz raqam guruh' => ['Olimga 150 000 qarz yoz', 'debt_add', 150000.0, null, 'olim', null],
            'qarz vergulli' => ['Olimga 150,000 qarz yoz', 'debt_add', 150000.0, null, 'olim', null],
            'to\'lov' => ['Ali 100 ming qarzini berdi', 'debt_payment', 100000.0, null, 'ali', null],
            'to\'lov 2' => ['Vali akadan 50 ming to\'lov oldim', 'debt_payment', 50000.0, null, 'vali', null],
            'to\'lov ru' => ['Али вернул 100 тысяч', 'debt_payment', 100000.0, null, 'али', null],
            'qarz ru' => ['Запиши долг Карим 200 тысяч', 'debt_add', 200000.0, null, 'карим', null],
            'kirim' => ['Futbolka 10 dona kirim qil', 'stock_in', null, 10.0, null, 'futbolka'],
            'kirim so\'z' => ['Shim besh dona kirim qil', 'stock_in', null, 5.0, null, 'shim'],
            'kirim ru' => ['Оприходуй футболка 20 штук', 'stock_in', null, 20.0, null, 'футболка'],
            'savdo' => ['Bugungi savdoni ko\'rsat', 'show_sales', null, null, null, null],
            'savdo ru' => ['Покажи продажи за сегодня', 'show_sales', null, null, null, null],
            'foyda' => ['Bugun qancha foyda qildim', 'show_profit', null, null, null, null],
            'qarzdorlar' => ['Kimlarning qarzi muddati o\'tgan', 'show_debts', null, null, null, null],
            'kam qoldiq' => ['Kam qolgan mahsulotlarni ko\'rsat', 'show_low_stock', null, null, null, null],
            'noma\'lum' => ['Salom qandaysiz', 'unknown', null, null, null, null],
        ];
    }

    public function test_transliterates_cyrillic_names(): void
    {
        $p = new VoiceCommandParser;
        $this->assertSame('ali', $p->transliterate('Али'));
        $this->assertSame('karim', $p->transliterate('Карим'));
        $this->assertSame('futbolka', $p->transliterate('футболка'));
    }
}
