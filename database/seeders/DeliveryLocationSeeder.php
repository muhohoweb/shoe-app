<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DeliveryLocation;

class DeliveryLocationSeeder extends Seeder
{
    public function run(): void
    {
        $towns = [
            // Major Cities
            ['town' => 'Nairobi',        'delivery_fee' => 300],
            ['town' => 'Mombasa',        'delivery_fee' => 600],
            ['town' => 'Kisumu',         'delivery_fee' => 500],
            ['town' => 'Nakuru',         'delivery_fee' => 400],
            ['town' => 'Eldoret',        'delivery_fee' => 500],

            // Nairobi Metro & Kiambu
            ['town' => 'Ruiru',          'delivery_fee' => 300],
            ['town' => 'Kikuyu',         'delivery_fee' => 300],
            ['town' => 'Thika',          'delivery_fee' => 300],
            ['town' => 'Athi River',     'delivery_fee' => 300],
            ['town' => 'Mlolongo',       'delivery_fee' => 300],
            ['town' => 'Syokimau',       'delivery_fee' => 300],
            ['town' => 'Juja',           'delivery_fee' => 350],
            ['town' => 'Kiambu',         'delivery_fee' => 300],
            ['town' => 'Limuru',         'delivery_fee' => 350],
            ['town' => 'Karuri',         'delivery_fee' => 300],
            ['town' => 'Ruaka',          'delivery_fee' => 300],

            // Rift Valley
            ['town' => 'Naivasha',       'delivery_fee' => 400],
            ['town' => 'Gilgil',         'delivery_fee' => 400],
            ['town' => 'Njoro',          'delivery_fee' => 450],
            ['town' => 'Elburgon',       'delivery_fee' => 450],
            ['town' => 'Turi',           'delivery_fee' => 450],
            ['town' => 'Narok',          'delivery_fee' => 450],
            ['town' => 'Kericho',        'delivery_fee' => 500],
            ['town' => 'Bomet',          'delivery_fee' => 500],
            ['town' => 'Eldama Ravine',  'delivery_fee' => 500],
            ['town' => 'Kabarnet',       'delivery_fee' => 550],
            ['town' => 'Kapsabet',       'delivery_fee' => 500],
            ['town' => 'Mai Mahiu',      'delivery_fee' => 400],

            // Western Kenya
            ['town' => 'Kakamega',       'delivery_fee' => 550],
            ['town' => 'Bungoma',        'delivery_fee' => 550],
            ['town' => 'Busia',          'delivery_fee' => 600],
            ['town' => 'Kitale',         'delivery_fee' => 550],
            ['town' => 'Mumias',         'delivery_fee' => 550],
            ['town' => 'Webuye',         'delivery_fee' => 550],
            ['town' => 'Kimilili',       'delivery_fee' => 550],
            ['town' => 'Malaba',         'delivery_fee' => 600],
            ['town' => 'Mbale',          'delivery_fee' => 550],

            // Nyanza
            ['town' => 'Kisii',          'delivery_fee' => 500],
            ['town' => 'Migori',         'delivery_fee' => 550],
            ['town' => 'Homa Bay',       'delivery_fee' => 550],
            ['town' => 'Siaya',          'delivery_fee' => 500],
            ['town' => 'Bondo',          'delivery_fee' => 500],
            ['town' => 'Rongo',          'delivery_fee' => 550],
            ['town' => 'Awendo',         'delivery_fee' => 550],
            ['town' => 'Oyugis',         'delivery_fee' => 550],
            ['town' => 'Isebania',       'delivery_fee' => 600],
            ['town' => 'Mbita',          'delivery_fee' => 600],
            ['town' => 'Nyamira',        'delivery_fee' => 500],
            ['town' => 'Kehancha',       'delivery_fee' => 600],

            // Central Kenya
            ['town' => 'Nyeri',          'delivery_fee' => 400],
            ['town' => 'Muranga',        'delivery_fee' => 350],
            ['town' => 'Karatina',       'delivery_fee' => 400],
            ['town' => 'Nanyuki',        'delivery_fee' => 450],
            ['town' => 'Nyahururu',      'delivery_fee' => 450],
            ['town' => 'Embu',           'delivery_fee' => 400],
            ['town' => 'Kerugoya',       'delivery_fee' => 400],
            ['town' => 'Kenol',          'delivery_fee' => 350],

            // Eastern Kenya
            ['town' => 'Meru',           'delivery_fee' => 450],
            ['town' => 'Chuka',          'delivery_fee' => 450],
            ['town' => 'Maua',           'delivery_fee' => 500],
            ['town' => 'Machakos',       'delivery_fee' => 350],
            ['town' => 'Kitui',          'delivery_fee' => 500],
            ['town' => 'Mwingi',         'delivery_fee' => 550],
            ['town' => 'Isiolo',         'delivery_fee' => 550],
            ['town' => 'Wote',           'delivery_fee' => 500],
            ['town' => 'Makindu',        'delivery_fee' => 500],
            ['town' => 'Emali',          'delivery_fee' => 450],
            ['town' => 'Garbatula',      'delivery_fee' => 650],

            // Coast
            ['town' => 'Malindi',        'delivery_fee' => 650],
            ['town' => 'Kilifi',         'delivery_fee' => 600],
            ['town' => 'Mtwapa',         'delivery_fee' => 600],
            ['town' => 'Ukunda',         'delivery_fee' => 650],
            ['town' => 'Diani',          'delivery_fee' => 700],
            ['town' => 'Watamu',         'delivery_fee' => 700],
            ['town' => 'Lamu',           'delivery_fee' => 800],
            ['town' => 'Voi',            'delivery_fee' => 600],
            ['town' => 'Taveta',         'delivery_fee' => 650],
            ['town' => 'Mariakani',      'delivery_fee' => 600],
            ['town' => 'Msambweni',      'delivery_fee' => 700],
            ['town' => 'Mazeras',        'delivery_fee' => 600],

            // Kajiado
            ['town' => 'Ngong',          'delivery_fee' => 300],
            ['town' => 'Kitengela',      'delivery_fee' => 300],
            ['town' => 'Kajiado',        'delivery_fee' => 400],
            ['town' => 'Kiserian',       'delivery_fee' => 300],
            ['town' => 'Namanga',        'delivery_fee' => 550],
            ['town' => 'Isinya',         'delivery_fee' => 400],

            // North Kenya
            ['town' => 'Lodwar',         'delivery_fee' => 900],
            ['town' => 'Kakuma',         'delivery_fee' => 950],
            ['town' => 'Marsabit',       'delivery_fee' => 900],
            ['town' => 'Moyale',         'delivery_fee' => 950],
            ['town' => 'Maralal',        'delivery_fee' => 750],
            ['town' => 'Wajir',          'delivery_fee' => 900],
            ['town' => 'Mandera',        'delivery_fee' => 950],
            ['town' => 'El Wak',         'delivery_fee' => 950],
            ['town' => 'Habaswein',      'delivery_fee' => 900],
            ['town' => 'Rhamu',          'delivery_fee' => 1000],
            ['town' => 'Takaba',         'delivery_fee' => 1000],
            ['town' => 'Lafey',          'delivery_fee' => 1000],
            ['town' => 'Banissa',        'delivery_fee' => 1000],

            // Garissa / Tana River
            ['town' => 'Garissa',        'delivery_fee' => 800],
            ['town' => 'Masalani',       'delivery_fee' => 850],
            ['town' => 'Hola',           'delivery_fee' => 850],

            // West Pokot / Nyandarua
            ['town' => 'Makutano',       'delivery_fee' => 600],
            ['town' => 'Mairo-Inya',     'delivery_fee' => 500],

            // Trans Nzoia
            ['town' => 'Kiminini',       'delivery_fee' => 550],
        ];

        foreach ($towns as $town) {
            DeliveryLocation::query()->updateOrCreate(
                ['town' => $town['town']],
                ['delivery_fee' => $town['delivery_fee'], 'is_active' => true]
            );
        }
    }
}