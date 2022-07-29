<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\GameQueue;
use App\Models\Cardinal;
use App\Models\User;
use App\Models\Game;
use App\Models\GameDat;
use App\Models\Player;
use App\Models\Dat;
use App\Models\Bidding;
use App\Models\Round;
use Illuminate\Support\Facades\Artisan;

/**
 * Games generator (artisan command)
 * 
 * @author Iban Cardona i Subiela
 */
class GenerateGames extends Command {
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:generate {--items=1 : Number of the objects to create} {--type=2 : Game type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create games from the games queue.';

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
        // Lock by cache
        Cache::lock('generategames')->get(function () {
            // Lock acquired indefinitely and automatically released...
    
            // Check max items
            $items = $this->option('items');
            $items = intval($items);
            if ($items > 100) {
                $this->info('Max 100 items!');
                $this->newLine();
                $items = 100;
            }

            // Check type
            $type = $this->option('type');
            if ($type != config('constants.game_types.tournament') && $type != config('constants.game_types.datclassification')) {
                $this->info('Type not valid!');
                return;
            }

            do {
                // Search 4 users in queue
                $game_queues = GameQueue::where('type', $type)->inRandomOrder()->take(4)->get();
                if ($game_queues->count() == 4) {
                    // Create game
                    $game = new Game();
                    $game->type = $type;
                    $game->status = config('constants.game_status.init');
                    $game->save();

                    // Create players
                    $cardinals = Cardinal::all();
                    $cardinals = $cardinals->shuffle();

                    $game_queues->each(function ($game_queue, $key) use ($cardinals, $game) {
                        $cardinal = $cardinals->shift();
                        $player = new Player();
                        $player->cardinal_id = $cardinal->id;
                        $player->user_id = $game_queue->user_id;
                        $player->game_id = $game->id;
                        $player->save();
                    });
                    //End create players

                    // Calculate couples
                    $game = Game::syncCouples($game->id);

                    // Delete users in the queue
                    $game_queues->each(function ($game_queue, $key) {
                        $game_queue->delete();
                    });

                    // Assign first Dat
                    $dat = null;
                    do {
                        Artisan::call('dat:create', ['--items' => 1]);
                        $dat = Dat::get_dat_never_played($game, Cardinal::inRandomOrder()->firstOrfail());
                    } while ($dat === null);
                    $game_dat = new GameDat();
                    $game_dat->game_id = $game->id;
                    $game_dat->dat_id = $dat->id;
                    $game_dat->number = 1;
                    $game_dat->save();

                    // Create empty biddings
                    Bidding::create_empty_biddings($game_dat);

                    // Create first round
                    Round::create_next_round($game_dat);

                    // Start game
                    $game->status = config('constants.game_status.started');
                    $game->save();
                }

                $items--;
            } while ($items > 0);
            
            return;
        });

        return 1;
    }
}
