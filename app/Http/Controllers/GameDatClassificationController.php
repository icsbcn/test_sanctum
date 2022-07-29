<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Player;
use App\Models\User;
use App\Models\Game;
use App\Models\Cardinal;
use App\Models\GameQueue;
use App\Models\GameDat;
use App\Models\Bidding;
use App\Models\Round;
use App\Models\Card;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

/**
 * GameDatClasssification Controller.
 * 
 * @author Iban Cardona i Subiela.
 */
class GameDatClassificationController extends Controller {

    /**
     * Register user in Dat Classification Waiting Room.
     * 
     * @param $request Illuminate\Http\Request.
     * @return json true
     */
    public function add_to_queue_datclassification(Request $request) {
        $user_id = intval($request->user()->id);

        // Check user is in the queue
        $game_queue = GameQueue::where('user_id', $user_id)->where('type', config('constants.game_types.datclassification'))->first();
        if (isset($game_queue->id) && intval($game_queue->id) > 0) {
            return response()->json(true);
        }

        // Add to queue
        $game_queue = new GameQueue();
        $game_queue->user_id = $user_id;
        $game_queue->type = config('constants.game_types.datclassification');
        $game_queue->save();
        
        // Generate game
        Artisan::call('game:generate', ['--items' => 1]);

        return response()->json(true);
    }

    /**
     * Check if we have active game data.
     * 
     * @param $request Illuminate\Http\Request.
     * @return json "GameDatClassificationArray" structure or json with null.
     */
    public function get_datclassification_game(Request $request) {
        $user_id = intval($request->user()->id);
        $game_status = config('constants.game_status.started');
        $game_type = config('constants.game_types.datclassification');

        $select = "
            SELECT g.id FROM games g
                INNER JOIN players p ON (p.user_id = :user_id AND p.game_id = g.id AND g.status = :game_status AND g.type = :game_type)
            LIMIT 1
        ";
        $results = DB::select(DB::raw($select), array(
            'user_id' => $user_id,
            'game_status' => $game_status,
            'game_type' => $game_type
        ));
        if (count($results) > 0) {
            $result = array_shift($results);
            $game = Game::find(intval($result->id));
            return response()->json($this->getGameDatClassificationArray($game));
        }

        return response()->json(null);
    }

    /**
     * Update bidding voice.
     * 
     * @param $request Illuminate\Http\Request.
     * @return json "GameDatClassificationArray" structure or json with null.
     */
    public function set_bidding(Request $request) {
        $user_id = intval($request->user()->id);
        $game_id = intval($request->input('game_id'));
        $bidding_id = intval($request->input('bidding_id'));
        $voice = $request->input('voice');
        $game_status = config('constants.game_status.started');

        // Check game
        $game = Game::find($game_id);
        if (!$game) {
            return response()->json(null);
        }

        // Check voices
        $voices = Bidding::get_voices();
        if (!in_array($voice, $voices)) {
            return response()->json(null);
        }

        // Check bidding
        $select = "
            SELECT b.id FROM biddings b
            INNER JOIN gamesdats gd ON (gd.id = b.gamedat_id AND b.id = :bidding_id)
            INNER JOIN cardinals c ON (c.id = b.cardinal_id)
            INNER JOIN games g ON (g.id = gd.game_id AND g.id = :game_id AND g.status = :game_status)
            INNER JOIN players p ON (p.game_id = g.id AND p.user_id = :user_id AND p.cardinal_id = c.id)
            LIMIT 1
        ";
        $results = DB::select(DB::raw($select), array(
            'bidding_id' => $bidding_id,
            'game_id' => $game_id,
            'game_status' => $game_status,
            'user_id' => $user_id
        ));
        if (count($results) > 0) {
            $result = $results[0];
            if ($result->id == $bidding_id) {
                // Update bidding and return json
                $bidding = Bidding::find($bidding_id);
                $bidding->voice = $voice;
                $bidding->save();

                return response()->json($this->getGameDatClassificationArray($game));
            }
        }

        return response()->json(null);
    }

    /**
     * Throw card to the game.
     * 
     * @param $request Illuminate\Http\Request.
     * @return json "GameDatClassificationArray" structure or json with error_message.
     */
    public function throw_card(Request $request) {
        $user_id = intval($request->user()->id);
        $game_id = intval($request->input('game_id'));
        $card_id = intval($request->input('card_id'));
        $round_id = intval($request->input('round_id'));
        $error = array();

        // Check card
        $card = Card::find($card_id);
        if (!$card) {
            $error['error_message'] = 'Card not found';
            return response()->json($error);
        }

        // Check game and player
        $game = Game::find($game_id);
        if (!$game) {
            $error['error_message'] = 'Game not found';
            return response()->json($error);
        }
        $players = $game->players;
        $player_found = false;
        foreach ($players as $player) {
            if ($player->user_id == $user_id) {
                $player_found = true;
                break;
            }
        }
        if ($player_found === false) {
            $error['error_message'] = 'Player not found';
            return response()->json($error);
        }

        // Check round
        $round = Round::find($round_id);
        if (!$round || $round->game_id != $game->id) {
            $error['error_message'] = 'Round not found';
            return response()->json($error);
        }
        $game_dat = GameDat::find($round->gamedat_id);

        // Check all biddings done
        if (Bidding::where('gamedat_id', $game_dat->id)->where('voice', '<>', '')->count() != 8) {
            $error['error_message'] = 'Biddings not completed';
            return response()->json($error);
        }

        // Check valid card
        if ($round->is_valid_card($card, $player) === false) {
            $error['error_message'] = 'Card not valid';
            return response()->json($error);
        }

        // Save card in the round in the right position.
        if ($round->card01_id == 0) {
            $round->card01_id = $card->id;
        } else if ($round->card02_id == 0) {
            $round->card02_id = $card->id;
        } else if ($round->card03_id == 0) {
            $round->card03_id = $card->id;
        } else if ($round->card04_id == 0) {
            $round->card04_id = $card->id;
        }
        $round->save();
        
        // next round or calculate points
        if ($round->card01_id > 0 && $round->card02_id > 0 && $round->card03_id > 0 && $round->card04_id > 0) {
            $round->update_points();
            
            if (Round::where('gamedat_id', $game_dat->id)->count() == 12) {
                // Finish game_dat
                $game_dat->close();
                
                // Finish game 
                $game->pointsNS = DB::table('gamesdats')->where('game_id', $game->id)->sum('pointsNS');
                $game->pointsEW = DB::table('gamesdats')->where('game_id', $game->id)->sum('pointsEW');
                $game->status = config('constants.game_status.finished');
                $game->save();
            } else {
                // Create Next round 
                Round::create_next_round($game_dat);
            }                
        }
         
        return response()->json($this->getGameDatClassificationArray($game));
    }

    /**
     * GameDatClassificationArray structure.
     * 
     * @param $game App\Models\Game
     * @return array
     */
    private function getGameDatClassificationArray(Game $game) {
        $arr_to_return = array();
        $arr_to_return['game_id'] = $game->id;
        $arr_to_return['pointsNS'] = $game->pointsNS;
        $arr_to_return['pointsEW'] = $game->pointsEW;
        $my_cardinal = null;

        // Players
        $arr_to_return['players'] = array();
        $arr_to_return['players']['whoAmI'] = null;
        $cardinals = Cardinal::all();
        foreach ($cardinals as $cardinal) {
            $arr_to_return['players'][$cardinal->name] = null;
        }
        
        $players = $game->players;
        foreach ($players as $player) {
            $user = $player->user;
            $cardinal_name = $player->cardinal->name;
            if ($user->id == Auth::id()) {
                $arr_to_return['players']['whoAmI'] = $cardinal_name;
                $my_cardinal = $player->cardinal;
            }
            $player_arr = array();
            $player_arr['user_id'] = $user->id;
            $player_arr['user_name'] = $user->name;
            $arr_to_return['players'][$cardinal_name] = $player_arr;
        }
        // End players

        // Points (all dats)
        $arr_to_return['points'] = array();
        $gamedats = GameDat::where('game_id', $game->id)->orderBy('number', 'asc')->get();
        foreach ($gamedats as $gamedat) {
            $dat_info = array();
            $dat_info['dat_number'] = $gamedat->number;
            $dat_info['pointsNS'] = $gamedat->pointsNS;
            $dat_info['pointsEW'] = $gamedat->pointsEW;
            array_push($arr_to_return['points'], $dat_info);
        }
        // End points (all dats)

        // Status game
        $arr_to_return['status'] = $game->status;
        
        // Last_dat
        $last_dat = GameDat::where('game_id', $game->id)->orderBy('number', 'desc')->first();
        $arr_to_return['last_dat'] = array();
        if ($last_dat) {
            $dat = $last_dat->dat;
            $arr_to_return['last_dat']['id'] = $last_dat->dat_id;
            $arr_to_return['last_dat']['number'] = $last_dat->number;
            $arr_to_return['last_dat']['who_speak'] = $dat->cardinalSingFirst->name; 
            $arr_to_return['last_dat']['my_cards'] = array();
            $user = Auth::user();
            $cards = $dat->cards;
            if ($cards) {
                foreach ($cards as $card) {
                    if ($card->pivot->cardinal_id == $my_cardinal->id) {
                        $card_array = array();
                        $card_array['card_id'] = $card->id;
                        $card_array['suit'] = $card->suit->name;
                        $card_array['number'] = $card->number;
                        array_push($arr_to_return['last_dat']['my_cards'], $card_array);
                    }
                }
            }
        }

        // Biddings
        $arr_to_return['last_dat']['biddings'] = array();
        $gamedat = GameDat::where('game_id', $game->id)
                    ->where('dat_id', $dat->id)
                    ->first();
        if ($gamedat) {
            $biddings = Bidding::where('gamedat_id', $gamedat->id)
                        ->orderBy('id')
                        ->get();
            if ($biddings) {
                foreach ($biddings as $key => $bidding) {
                    $arr_to_return['last_dat']['biddings'][$key]['id'] = $bidding->id;
                    $arr_to_return['last_dat']['biddings'][$key]['cardinal'] = $bidding->cardinal->name;
                    $arr_to_return['last_dat']['biddings'][$key]['voice'] = $bidding->voice;
                }
            }
        }

        $arr_to_return['last_dat']['rounds'] = array();
        if ($gamedat) {
            $rounds = Round::where('game_id', $game->id)->where('gamedat_id', $gamedat->id)->get();
            if ($rounds) {
                foreach ($rounds as $key => $round) {
                    $arr_to_return['last_dat']['rounds'][$key]['id'] = $round->id;
                    $arr_to_return['last_dat']['rounds'][$key]['who_start_id'] = $round->who_start_id;
                    $arr_to_return['last_dat']['rounds'][$key]['card01_id'] = $round->card01_id;
                    $arr_to_return['last_dat']['rounds'][$key]['card02_id'] = $round->card02_id;
                    $arr_to_return['last_dat']['rounds'][$key]['card03_id'] = $round->card03_id;
                    $arr_to_return['last_dat']['rounds'][$key]['card04_id'] = $round->card04_id;
                    $arr_to_return['last_dat']['rounds'][$key]['pointsEW'] = $round->pointsEW;
                    $arr_to_return['last_dat']['rounds'][$key]['pointsNS'] = $round->pointsNS;
                }
            }
        }
        // End

        return $arr_to_return;
    }
}
