<?php

namespace Tests;
use Illuminate\Testing\Fluent\AssertableJson;

final class LibraryTests {
    
    public static function checkGameDatClassificationStructure(TestCase $t, $response, $items_to_check = array()) {
        // Check specific values
        foreach ($items_to_check as $key => $value) {
            $response->assertJsonPath($key, $value);
        }

        // Check structure
        $t->assertGreaterThan(0, $response['game_id']);
        $t->assertIsNumeric($response['pointsNS']);
        $t->assertIsNumeric($response['pointsEW']);

        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('players', 5) // 4 players + whoAmI
                 ->etc()
        );
        $players = $response['players'];
        $t->assertNotEmpty($players['whoAmI']);
        $t->assertIsArray($players[config('constants.cardinals.north')]);
        $t->assertIsArray($players[config('constants.cardinals.south')]);
        $t->assertIsArray($players[config('constants.cardinals.east')]);
        $t->assertIsArray($players[config('constants.cardinals.west')]);

        // Check last_Dat
        $last_dat = $response['last_dat'];
        $t->assertIsArray($last_dat);
        $t->assertNotEmpty($last_dat['id']);
        $t->assertNotEmpty($last_dat['number']);
        $t->assertNotEmpty($last_dat['who_speak']);
        $mycards = $last_dat['my_cards'];
        $t->assertIsArray($mycards);
        $t->assertCount(12, $mycards);

        $biddings = $last_dat['biddings'];
        $t->assertCount(8, $biddings); // 8 biddings
        
        //TODO: seguir per aqui avaluant cada cas

        /*$response
            ->assertJsonPath('game_id', $game->id)
            ->assertJsonPath('pointsNS', 0)
            ->assertJsonPath('pointsEW', 0)
            ->assertJsonPath('status', config('constants.game_status.started'))
            ->assertJsonPath('players.whoAmI', config('constants.cardinals.north'))
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('players', 5) // 4 players + whoAmI
                     ->has('players.'.config('constants.cardinals.north'), fn ($json) =>
                            $json->where('user_id', $user->id)
                             ->where('user_name', $user->name)
                             ->where('status', config('constants.player_status.pending'))
                        )
                     ->etc()
            );*/
    }
}
