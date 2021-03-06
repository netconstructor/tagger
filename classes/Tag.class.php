<?php

require_once __ROOT__ . 'classes/Token.class.php';

/*
 * Tags are the overall tags found in the text, i.e. tags are unique.
 * The tag points to the specific places in the text where it was found ($tokens)
 * and has a rating that is the sum of the ratings of the individual tokens.
 */
class Tag extends Token {

  public $tokens = array();

  public $realName;
  public $synonyms = array();

  public $posRating = 0;
  public $freqRating = 0;

  public $type = 'named_entity'; // keyword, named_entity

  public function __construct($token) {
    if (is_string($token)) {
      parent::__construct($token);
    }
    elseif (is_a($token, 'Token')) {
      parent::__construct($token->text);
    }
    else {
      parent::__construct('');
    }

    if($this->text != '') {
      $this->synonyms[] = $this->text;
    }
  }

  public static function mergeTags($tags, $real_name = '') {
    $ret_tag = new Tag($real_name);
    $ret_tag->realName = $real_name;
    foreach ($tags as $tag) {
      $ret_tag->synonyms = array_unique(array_merge($ret_tag->synonyms, $tag->synonyms));
      $ret_tag->tokens   = array_merge_recursive($ret_tag->tokens, $tag->tokens);
      if(isset($tag->ambiguous)) {
        $ret_tag->ambiguous = $tag->ambiguous;
      }
      if(isset($tag->meanings)) {
        $ret_tag->meanings = $tag->meanings;
      }
    }
    return $ret_tag;
  }

  public function rate() {
    foreach ($this->tokens as $synonym_tokens) {
      foreach ($synonym_tokens as $token) {
        $this->freqRating++;
        $this->rating     += $token->rating;
        $this->posRating  += $token->posRating;
        $this->htmlRating += $token->htmlRating;
      }
    }

    $freq_rating = Tagger::getConfiguration($this->type, 'rating', 'frequency');
    $this->rating /= 1 + (($this->freqRating - 1) * (1 - $freq_rating));

    // Normalization
    $this->rating *= 10;
    // Clamp within 0-100%
    $this->rating = max(0, $this->rating);
    $this->rating = min($this->rating, 100);
  }

  public function __toString() {
    $str =  "Tag: $this->realName\n";
    $str .= "Synonyms: " . implode(', ', $this->synonyms) . "\n";
    $str .= "Rating: $this->rating (freq: $this->freqRating, pos: $this->posRating, html: $this->htmlRating)\n";
    return $str;
  }

  public static function mergeTokens($tokens) {
    $tags = array();

    for ($i = 0, $n = count($tokens)-1; $i <= $n; $i++) {
      if (isset($tokens[$i])) {
        $tag = new Tag($tokens[$i]);
        for ($j = $i; $j <= $n; $j++) {
          if (isset($tokens[$j]) && $tag->text == $tokens[$j]->text) {
            $tag->tokens[$tag->text][] = &$tokens[$j];
            unset($tokens[$j]);
          }
        }
        $tags[] = $tag;
      }
    }

    return $tags;
  }

}


