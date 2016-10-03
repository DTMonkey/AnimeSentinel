<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;

use App\Show;
use App\AnimeSentinel\Actions\ShowManager;

class ShowController extends Controller
{
  /**
   * Show the details page for a show.
   *
   * @return \Illuminate\Http\Response
   */
  public function details($show_id, $title = '') {
    $show = Show::getShowFromUrl($show_id, $title);

    if (isset($show)) {
      if ($show->id === (int) $show_id && $title === slugify($show->title)) {
        if (!visitPage('show_'.$show->id)) {
          $show->hits++;
          $show->save();
        }
        return view('anime.details', [
          'show' => $show,
        ]);
      }
      else {
        return redirect($show->details_url);
      }
    }

    abort(404);
  }
}
