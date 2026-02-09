<?php

namespace App\Model\Nsi;

use DateTime;
use App\Request\SqlRequest;

class Score {
    public string $username;
    public array $stars = array();

    public function getTotalStars(): int {
        return array_sum(array_map(function(Star $star) { return $star->amount; }, $this->stars));
    }

    public function getTotalBasicStars(): int {
        return array_sum(array_map(function(Star $star) { return $star->specialty == "BASIC" ? $star->amount : 0; }, $this->stars));
    }

    public function getTotalDiamondStars(): int {
        return array_sum(array_map(function(Star $star) { return $star->specialty == "DIAMOND" ? $star->amount : 0; }, $this->stars));
    }

    public function getTotalGoldStars(): int {
        return array_sum(array_map(function(Star $star) { return $star->specialty == "GOLD" ? $star->amount : 0; }, $this->stars));
    }

    public static function fetch_best_scores(int $limit): array {
        // Récupération des meilleurs profils.
        $responses = SqlRequest::new(<<< EOF
            SELECT
                username,
                total_stars
            FROM (
                SELECT
                    username,
                    SUM(stars_count) AS total_stars
                FROM (
                    SELECT
                        username,
                        challenge_id,
                        dt,
                        stars_count
                    FROM
                        api_nsi_stars
                            JOIN
                        api_nsi_challenges
                            ON api_nsi_stars.challenge_id = api_nsi_challenges.id
                    ) AS all_stars_by_username
                GROUP BY
                    username
                ORDER BY
                    total_stars
                ) stars_of_username
                    NATURAL JOIN
                api_nsi_profiles
            WHERE
                displayable = 1
            LIMIT ?;
            EOF
        )->execute([$limit]);

        $best_scores = array();
        foreach ($responses as $response) {
            $best_scores[$response->username] = new Score();
            $best_scores[$response->username]->username = $response->username;
        }

        // Récupération des étoiles des profils.
        $marker_str = implode(", ", array_fill(0, count($best_scores), '?'));
        $responses = SqlRequest::new(<<< EOF
            (
                SELECT
                    username,
                    challenge_id,
                    dt,
                    stars_count,
                    title,
                    CASE
                        WHEN (challenge_id, dt) IN (
                            SELECT
                                challenge_id, MIN(dt)
                            FROM
                                api_nsi_stars JOIN api_nsi_profiles ON api_nsi_stars.username = api_nsi_profiles.username
                            WHERE
                                displayable = 1
                            GROUP BY challenge_id
                        ) THEN "DIAMOND"
                        WHEN CURRENT_TIMESTAMP() < gold_deadline_dt THEN "GOLD"
                        ELSE "BASIC"
                    END AS specialty
                FROM
                    api_nsi_stars JOIN api_nsi_challenges ON api_nsi_stars.challenge_id = api_nsi_challenges.id
                WHERE username IN ('toto', 'lulu')
            )
                UNION
            (
                SELECT
                    username,
                    event_id AS challenge_id,
                    end_dt AS dt,
                    stars_count,
                    title,
                    "SPECIAL"
                FROM
                    api_nsi_special_stars JOIN api_nsi_events ON api_nsi_special_stars.event_id = api_nsi_events.id
            );
            EOF
        )->execute(array_keys($best_scores));

        foreach ($responses as $response) {
            $star = new Star();
            $star->challenge_id = $response->challenge_id;
            $star->challenge_title = $response->title;
            $star->dt = new DateTime($response->dt);
            $star->specialty = $response->specialty;
            $star->amount = $response->stars_count;
            array_push($best_scores[$response->username]->stars, $star);
        }

        return array_values($best_scores);
    }
}
