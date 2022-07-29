<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cardinal;
use App\Models\Card;
use App\Models\Dat;
use Illuminate\Support\Facades\DB;

/**
 * Dat generator (artisan command)
 * 
 * @author Iban Cardona i Subiela
 */
class CreateDats extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dat:create {--items=1 : Number of the objects to create} {--confirm=0 : Require confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create random dats';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        // Get options
        $items = $this->option('items');
        $items = intval($items);
        if ($items > 100) {
            $this->info('Max 100 items!');
            $this->newLine();
            $items = 100;
        }
        $confirm = $this->option('confirm');
        if (intval($confirm) == 1) {
            if (!$this->confirm('Do you want to create '.$items.' dat/s?')) {
                $this->info('Bye!');
                return 0;
            }
        }

        $cardinal_north_id = DB::table('cardinals')->where('name', config('constants.cardinals.north'))->value('id');
        $cardinal_east_id = DB::table('cardinals')->where('name', config('constants.cardinals.east'))->value('id');
        $cardinal_south_id = DB::table('cardinals')->where('name', config('constants.cardinals.south'))->value('id');
        $cardinal_west_id = DB::table('cardinals')->where('name', config('constants.cardinals.west'))->value('id');

        $bar = $this->output->createProgressBar($items);
        $bar->start();
        do {
            // Generate random cards and random cardinal_start
            $cards = Card::all()->random(48)->transform(function ($item, $key) {
                return $item->id;
            })->toArray();
            $cardinal = Cardinal::all()->random();
            $north_cards_ids = array_slice($cards, 0, 12);
            $east_cards_ids = array_slice($cards, 11, 12);
            $west_cards_ids = array_slice($cards, 23, 12);
            $south_cards_ids = array_slice($cards, 35, 12);
            
            // Search dat
            $dat = Dat::get_dat_by_cards($cardinal, $north_cards_ids, $east_cards_ids, $west_cards_ids, $south_cards_ids);
            if ($dat === null) {
                // Create dat
                $new_dat = new Dat;
                $new_dat->cardinalSingFirst_id = $cardinal->id;
                $new_dat->save();

                $new_dat->cards()->syncWithPivotValues($north_cards_ids, ['cardinal_id' => $cardinal_north_id]);
                $new_dat->cards()->attach([
                    $east_cards_ids[0] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[1] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[2] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[3] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[4] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[5] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[6] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[7] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[8] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[9] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[10] => ['cardinal_id' => $cardinal_east_id],
                    $east_cards_ids[11] => ['cardinal_id' => $cardinal_east_id]
                ]);
                $new_dat->cards()->attach([
                    $west_cards_ids[0] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[1] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[2] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[3] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[4] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[5] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[6] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[7] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[8] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[9] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[10] => ['cardinal_id' => $cardinal_west_id],
                    $west_cards_ids[11] => ['cardinal_id' => $cardinal_west_id]
                ]);
                $new_dat->cards()->attach([
                    $south_cards_ids[0] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[1] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[2] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[3] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[4] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[5] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[6] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[7] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[8] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[9] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[10] => ['cardinal_id' => $cardinal_south_id],
                    $south_cards_ids[11] => ['cardinal_id' => $cardinal_south_id]
                ]);

                $this->newLine(2);
                $this->info('New Dat with ID '.$new_dat->id.' created!');
                $this->newLine();

                $bar->advance();
                
                $items--;
            }
        } while ($items > 0);
        $bar->finish();

        $this->newLine(2);
        $this->info('End!');

        return 1;
    }
}
