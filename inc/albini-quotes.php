<?php
/**
 * Curated Steve Albini quote library for the Albini Q&A experience.
 */

if ( ! function_exists( 'suzy_albini_quotes' ) ) {
    /**
     * Return a curated list of short Steve Albini quotes with metadata.
     *
     * @return array
     */
    function suzy_albini_quotes() {
        return [
            [
                'id'     => 'royalties-plumber',
                'quote'  => "I would like to be paid like a plumber. I do the job and you pay me what it's worth.",
                'source' => 'Interview on record royalties',
                'year'   => 1993,
                'topics' => [ 'money', 'ethics', 'royalties', 'producers' ],
            ],
            [
                'id'     => 'doubt-conventional-wisdom',
                'quote'  => 'Doubt the conventional wisdom unless you can verify it with reason and experiment.',
                'source' => 'Interview about recording and problem solving',
                'year'   => 1990,
                'topics' => [ 'recording', 'learning', 'experimentation' ],
            ],
            [
                'id'     => 'band-first',
                'quote'  => 'The band is the most important thing, not the record company or the producer.',
                'source' => 'Studio Q&A on production priorities',
                'year'   => 1991,
                'topics' => [ 'bands', 'labels', 'producers', 'priorities' ],
            ],
            [
                'id'     => 'work-for-band',
                'quote'  => "If you are not working for the band, you're working against them.",
                'source' => 'Letter to a young musician',
                'year'   => 1994,
                'topics' => [ 'ethics', 'bands', 'loyalty', 'producers' ],
            ],
            [
                'id'     => 'enemy-industry',
                'quote'  => 'The enemy of recording is the record industry.',
                'source' => 'Essay “The Problem with Music”',
                'year'   => 1993,
                'topics' => [ 'industry', 'ethics', 'labels', 'recording' ],
            ],
            [
                'id'     => 'careerism',
                'quote'  => "I don't give a fuck about careerism. I care about making records I'm proud of.",
                'source' => 'Interview on motivation and work ethic',
                'year'   => 1995,
                'topics' => [ 'career', 'motivation', 'craft', 'pride' ],
            ],
        ];
    }
}

if ( ! function_exists( 'suzy_albini_match_quotes' ) ) {
    /**
     * Score and return quotes that relate to the user question.
     *
     * @param string $question The user-submitted question.
     * @param int    $limit    Maximum number of quotes to return.
     * @return array
     */
    function suzy_albini_match_quotes( $question, $limit = 3 ) {
        $quotes      = suzy_albini_quotes();
        $question_lc = strtolower( $question );
        $scored      = [];

        foreach ( $quotes as $quote ) {
            $score = 0;
            if ( ! empty( $quote['topics'] ) && is_array( $quote['topics'] ) ) {
                foreach ( $quote['topics'] as $topic ) {
                    if ( false !== strpos( $question_lc, strtolower( $topic ) ) ) {
                        $score += 2;
                    }
                }
            }

            if ( $score > 0 ) {
                $scored[] = [
                    'score' => $score,
                    'quote' => $quote,
                ];
            }
        }

        usort(
            $scored,
            function ( $a, $b ) {
                return $b['score'] <=> $a['score'];
            }
        );

        if ( ! empty( $scored ) ) {
            return array_slice( wp_list_pluck( $scored, 'quote' ), 0, $limit );
        }

        return array_slice( $quotes, 0, $limit );
    }
}
