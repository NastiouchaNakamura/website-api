<?php

namespace App\Model\Nsi;

use App\Request\SqlRequest;
use DateTime;

class Challenge {
    public string $id;
    public string $flag;
    public string $stars_count;
    public string $title;
    public DateTime $gold_deadline_dt;

    public static function fetch(string $id): Challenge|null {
        $id = str_replace(" ", "", trim($id));
        if (empty($id)) return null;
        
        $responses = SqlRequest::new(<<< EOF
            SELECT
                id,
                flag,
                stars_count,
                title,
                gold_deadline_dt
            FROM
                api_nsi_challenges
            WHERE id = ?;
            EOF
        )->execute([$id]);

        if (empty($responses)) {
            return null;
        } else {
            $challenge = new Challenge();
            $challenge->id = $responses[0]->id;
            $challenge->flag = $responses[0]->flag;
            $challenge->stars_count = $responses[0]->stars_count;
            $challenge->title = $responses[0]->title;
            $challenge->gold_deadline_dt = new DateTime($responses[0]->gold_deadline_dt);
            return $challenge;
        }
    }
}
