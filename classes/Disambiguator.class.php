<?php

class Disambiguator {

  private $tags;

  public function __construct($tags, $text) {
    $this->tags = $tags;
    $this->tag_ids = array();
    $this->text = $text;
    foreach ($this->tags as $vocabulary) {
      foreach($vocabulary as $tid => $tag) {
        $this->tag_ids[] = $tid;
      }
    }
  }
  public function disambiguate() {

    if (!isset($this->tags)) {
      return;
    }
    foreach ($this->tags as $vocabulary => $tids) {
      foreach($tids as $tid => $tag) {
        if ($tag->ambiguous) {
          $checked_tid = $this->checkRelatedWords($tag);
          if ($checked_tid != 0 && $checked_tid != $tid) {
            $temp = $this->tags[$vocabulary][$tid];
            $this->tags[$this->getVocabulary($checked_tid)][$checked_tid] = $temp;
            unset($this->tags[$vocabulary][$tid]);
          }
        }
      }
    }
    return $this->tags;
  }

  public function checkRelatedWords($tag) {
    $related_words = $this->getRelatedWords($tag);
    $max_matches = 0;
    $current = 0;

    foreach ($related_words as $tid => $words) {
      $subtotal = 0;
      foreach ($words as $word) {
        
        //Check for each related word how many times it occurs in the text
        $subtotal += substr_count($this->text, $word);
        if($subtotal > $max_matches) {
          $current = $tid;
          $max_matches = $subtotal;
        }
      }
    }
    return $current;
  }
  public function getRelatedWords($tag) {
    $tagger_instance = Tagger::getTagger();
    $db_conf = $tagger_instance->getConfiguration('db');
    $disambiguation_table = $db_conf['disambiguation_table'];

    $sql = sprintf("SELECT r.tid, GROUP_CONCAT(r.name) AS words FROM $disambiguation_table AS r WHERE r.tid IN (%s) GROUP BY r.tid", $tag->meanings);
    $matches = array();
    $result = TaggerQueryManager::query($sql);
    if ($result) {
      while ($row = TaggerQueryManager::fetch($result)) {
        $matches[$row['tid']] = explode(',', $row['words']);
      }
    }
    return $matches;
  }

  public function getVocabulary($tid) {
    $tagger_instance = Tagger::getTagger();
    $db_conf = $tagger_instance->getConfiguration('db');
    $lookup_table = $db_conf['lookup_table'];

    $sql = sprintf("SELECT c.vid FROM $lookup_table AS c WHERE c.tid = %s LIMIT 0,1", $tid);
    $result = TaggerQueryManager::query($sql);
    $row = TaggerQueryManager::fetch($result);
    $vocab_names = $tagger_instance->getConfiguration('vocab_names');
    return $row['vid'];
  }
}

