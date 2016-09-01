<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\AnimeSentinel\ShowManager;

class ShowAddMal implements ShouldQueue
{
  use InteractsWithQueue, Queueable, SerializesModels;
  public $db_data;

  protected $mal_id;
  protected $targetQueue;

  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct($mal_id, $targetQueue = 'default') {
    $this->mal_id = $mal_id;
    $this->targetQueue = $targetQueue;
    // Set special database data
    $this->db_data = [
      'job_task' => 'ShowAdd',
      'show_id' => $mal_id,
      'job_data' => null,
    ];
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle() {
    ShowManager::addShowWithMalId($this->mal_id, $this->targetQueue);
  }
}