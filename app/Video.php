<?php

namespace App;

use App\AnimeSentinel\Actions\VideoManager;
use Carbon\Carbon;

class Video extends BaseModel
{
  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'show_id', 'translation_type', 'episode_num', 'streamer_id', 'link_episode', 'link_stream', 'notes', 'link_video', 'mirror_id', 'uploadtime', 'resolution',
  ];

  /**
   * The attributes that should be mutated to dates.
   *
   * @var array
   */
  protected $dates = ['uploadtime', 'created_at', 'updated_at'];

  /**
   * Overwrite the save method to properly handle the compound key.
   */
  public function save(array $options = []) {
    if (empty($this->mirror)) {
      $max_mirror = Self::where('streamer_id', $this->streamer_id)
                        ->where('show_id', $this->show_id)
                        ->where('translation_type', $this->translation_type)
                        ->where('episode_num', $this->episode_num)
                        ->max('mirror');
      $this->mirror = $max_mirror + 1;
    }
    parent::save($options);
  }

  /**
   * Overwrite the find method to properly handle the compound key.
   */
  public static function find($data) {
    if (is_array($data)) {
      return Self::where('show_id', $data['show_id'])
                 ->where('translation_type', $data['translation_type'])
                 ->where('episode_num', $data['episode_num'])
                 ->where('streamer_id', $data['streamer_id'])
                 ->where('mirror', $data['mirror'])
                 ->first();
    }
    else {
      return Self::where('id', $data)->first();
    }
  }

  /**
  * Get the show this video belongs to.
  *
  * @return \Illuminate\Database\Eloquent\Relations\Relation
  */
  public function show() {
    return $this->belongsTo(Show::class);
  }

  /**
  * Get the streamer this video belongs to.
  *
  * @return \Illuminate\Database\Eloquent\Relations\Relation
  */
  public function streamer() {
    return $this->belongsTo(Streamer::class);
  }

  /**
  * Get all streamers that stream this episode.
  */
  public function getStreamersAttribute() {
    $show = $this;
    return Streamer::whereIn('id', function ($query) use ($show) {
                       $query->select('streamer_id')
                             ->from('videos')
                             ->where('show_id', $show->show_id)
                             ->where('translation_type', $show->translation_type)
                             ->where('episode_num', $show->episode_num);
                     })->get();
  }

  /**
   * Scope a query to only include video's for this episode.
   *
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function scopeEpisode($query, $translation_type, $episode_num) {
    return $query->where('translation_type', $translation_type)->where('episode_num', $episode_num);
  }

  /**
  * Get a string that uniquely identifies this episode.
  *
  * @return string
  */
  public function getEpisodeIdAttribute() {
    if (isset($this->mal_id)) {
      return json_encode([
        'mal_id' => $this->mal_id,
        'translation_type' => $this->translation_type,
        'episode_num' => $this->episode_num,
      ]);
    } else {
      return json_encode([
        'show_id' => $this->show_id,
        'translation_type' => $this->translation_type,
        'episode_num' => $this->episode_num,
      ]);
    }
  }

  /**
  * Get the full url for this episode page.
  *
  * @return string
  */
  public function getEpisodeUrlAttribute() {
    return fullUrl('/anime/'.$this->show_id.'/'.slugify($this->show->title).'/'.$this->translation_type.'/episode-'.$this->episode_num);
  }

  /**
  * Get a static url for this episode page.
  *
  * @return string
  */
  public function getEpisodeUrlStaticAttribute() {
    return fullUrl('/anime/-/'.slugify($this->show->title).'/'.$this->translation_type.'/episode-'.$this->episode_num, true);
  }

  /**
  * Get the full url for the episode page with this mirror selected.
  *
  * @return string
  */
  public function getStreamUrlAttribute() {
    return fullUrl('/anime/'.$this->show_id.'/'.slugify($this->show->title).'/'.$this->translation_type.'/episode-'.$this->episode_num.'/'.$this->streamer_id.'/'.$this->mirror);
  }

  /**
  * Get a static url for the episode page with this mirror selected.
  *
  * @return string
  */
  public function getStreamUrlStaticAttribute() {
    return fullUrl('/anime/-/'.slugify($this->show->title).'/'.$this->translation_type.'/episode-'.$this->episode_num.'/'.$this->streamer_id.'/'.$this->mirror, true);
  }

  /**
  * Get the surface area of the video.
  *
  * @return integer
  */
  public function getVideoSurfaceAttribute() {
    $resolutions = explode('x', $this->resolution);
    return $resolutions[0] * $resolutions[1];
  }

  /**
  * Get the aspect ratio of the video.
  *
  * @return integer
  */
  public function getVideoAspectAttribute() {
    $resolutions = explode('x', $this->resolution);
    return $resolutions[1] / $resolutions[0];
  }

  /**
  * Return whether the video link is supported by the HTML5 player.
  *
  * @return boolean
  */
  public function getPlayerSupportAttribute() {
    if (str_ends_with($this->link_video, '.html')) {
      return false;
    }
    return true;
  }

  /**
  * Return the 'best' out of all mirrors for this episode.
  *
  * @return string
  */
  public function getBestMirrorAttribute() {
    $mirrors = Self::where('show_id', $this->show_id)->episode($this->translation_type, $this->episode_num)->get();
    $bestMirror = null;

    foreach ($mirrors as $mirror) {
      if ($mirror->player_support && $mirror->encoding !== 'broken' && ($bestMirror === null || $mirror->video_surface > $bestMirror->video_surface)) {
        $bestMirror = $mirror;
      }
    }
    if ($bestMirror === null) {
      foreach ($mirrors as $mirror) {
        if ($mirror->encoding !== 'broken' && ($bestMirror === null || $mirror->video_surface > $bestMirror->video_surface)) {
          $bestMirror = $mirror;
        }
      }
    }

    if ($bestMirror === null) {
      foreach ($mirrors as $mirror) {
        if ($bestMirror === null || $mirror->video_surface > $bestMirror->video_surface) {
          $bestMirror = $mirror;
        }
      }
    }

    return $bestMirror;
  }

  /**
  * Get the full url for the episode page for the previous episode.
  *
  * @return string
  */
  public function getPreEpisodeUrlAttribute() {
    $episodes = Self::where('show_id', $this->show_id)->where('translation_type', $this->translation_type)
                      ->distinctOn('episode_num')->orderBy('episode_num')->get();
    $thisKey = $episodes->where('episode_num', $this->episode_num)->keys()->first();

    if (isset($episodes[$thisKey - 1])) {
      return $episodes[$thisKey - 1]->episode_url;
    } else {
      return null;
    }
  }

  /**
  * Get the full url for the episode page for the next episode.
  *
  * @return string
  */
  public function getNextEpisodeUrlAttribute() {
    $episodes = Self::where('show_id', $this->show_id)->where('translation_type', $this->translation_type)
                      ->distinctOn('episode_num')->orderBy('episode_num')->get();
    $thisKey = $episodes->where('episode_num', $this->episode_num)->keys()->first();

    if (isset($episodes[$thisKey + 1])) {
      return $episodes[$thisKey + 1]->episode_url;
    } else {
      return null;
    }
  }

  /**
  * Refresh the link_video for this video when it is needed.
  */
  public function refreshVideoLink() {
    if ($this->player_support) {
      $data = json_decode(shell_exec('ffprobe -v quiet -print_format json -show_format "'. $this->link_video .'"'));
      if (json_encode($data) === '{}') {
        $this->link_video = VideoManager::findVideoLink($this);
        $this->setVideoMetaData();
      }
      elseif ($this->encoding === 'broken' || $this->encoding === 'embed' || $this->encoding === null) {
        $this->setVideoMetaData();
      }
      if ($this->encoding === 'broken') {
        queueJob(new \App\Jobs\AnimeReprocessEpisodes($this->show, [$this->translation_type], $this->episode_num, $this->streamer_id));
      }
    }
  }

  /**
   * Finds and saves the metadata for this video.
   *
   * @return boolean
   */
  public function setVideoMetaData($tries = 1) {
    if (empty($this->link_video)) {
      $this->encoding = 'broken';
      return false;
    }

    elseif ($this->player_support) {
      $data = json_decode(shell_exec('ffprobe -v quiet -print_format json -show_streams -show_format "'. $this->link_video .'"'));

      if (!isset($data->streams) || !isset($data->format)) {
        if ($tries >= 9) {
          $this->encoding = 'broken';
          return false;
        } else {
          return $this->setVideoMetaData($tries + 1);
        }
      }

      foreach ($data->streams as $stream) {
        if ($stream->codec_type === 'video') {
          $this->resolution = $stream->width.'x'.$stream->height;
          break;
        }
      }
      $this->duration = $data->format->duration;
      $this->encoding = 'video/'.explode(',', $data->format->format_name)[0];
    }

    else {
      $this->encoding = 'embed';
    }

    return true;
  }
}
