<?php
/**
 * Copyright (C) 2009-2012 Graham Breach
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * For more information, please contact <graham@goat1000.com>
 */

require_once 'SVGGraph3DGraph.php';

class Bar3DGraph extends ThreeDGraph {

  protected $bar_styles = array();
  protected $label_centre = true;

  protected $bx;
  protected $by;
  protected $block_width;

  protected function Draw()
  {
    // make sure project_angle is in range
    if($this->project_angle < 0)
      $this->project_angle = 0;
    elseif($this->project_angle > 90)
      $this->project_angle = 90;

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $values = $this->GetValues();
    $this->block_width = $this->bar_unit_width - $this->bar_space;

    // make the top parallelogram, set it as a symbol for re-use
    list($this->bx, $this->by) = $this->Project(0, 0, $this->block_width);
    $top = $this->BarTop();

    $bnum = 0;
    $bspace = $this->bar_space / 2;
    $ccount = count($this->colours);

    // get the translation for the whole bar
    list($tx, $ty) = $this->Project(0, 0, $this->bar_space / 2);
    $group = array('transform' => "translate($tx,$ty)");
    $bar = array('width' => $this->block_width);

    $bars = '';
    foreach($values as $key => $value) {
      $bar_pos = $this->GridPosition($key, $bnum);

      if(!is_null($value) && !is_null($bar_pos)) {
        $bar['x'] = $bspace + $bar_pos;
        $colour = $bnum % $ccount;

        $bar_sections = $this->Bar3D($value, $bar, $top, $colour);
        $link = $this->GetLink($key, $bar_sections);

        $group['fill'] = $this->GetColour($colour);
        if($this->show_tooltips)
          $this->SetTooltip($group, $value);
        $bars .= $this->Element('g', $group, NULL, $link);
        unset($group['id']); // make sure a new one is generated
        $style = $group;
        $this->SetStroke($style);
        $this->bar_styles[] = $style;
      }
      ++$bnum;
    }

    $bgroup = array('fill' => 'none');
    $this->SetStroke($bgroup, 'round');
    $body .= $this->Element('g', $bgroup, NULL, $bars);
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
  }

  /**
   * Returns the bar top path details array
   */
  protected function BarTop()
  {
    $top_id = $this->NewID();
    $top = array(
      'id' => $top_id,
      'd' => "M0,0 l{$this->block_width},0 l{$this->bx},{$this->by} l-{$this->block_width},0 z"
    );
    $this->defs[] = $this->Element('symbol', NULL, NULL,
      $this->Element('path', $top));
    return array('xlink:href' => '#' . $top_id);
  }

  /**
   * Returns the SVG code for a 3D bar
   */
  protected function Bar3D($value, &$bar, &$top, $colour, $start = null)
  {
    $this->Bar($value, $bar, $start);

    $side_x = $bar['x'] + $this->block_width;
    $side = array(
      'd' => "M0,0 l{$this->bx},{$this->by} l0,$bar[height] l-{$this->bx}," . -$this->by . " z",
      'transform' => "translate($side_x,$bar[y])"
    );
    if(is_null($top)) {
      $bar_top = '';
    } else {
      $top['transform'] = "translate($bar[x],$bar[y])";
      $top['fill'] = $this->GetColour($colour, TRUE);
      $bar_top = $this->Element('use', $top);
    }

    $rect = $this->Element('rect', $bar);
    $bar_side = $this->Element('path', $side);
    return $rect . $bar_top . $bar_side;
  }

  /**
   * Fills in the y-position and height of a bar (copied from BarGraph)
   */
  protected function Bar($value, &$bar, $start = null)
  {
    $y = $this->height - $this->pad_bottom - $this->y0;
    if(!is_null($start))
      $y -= $start;
    $l1 = $this->ClampVertical($y);
    $l2 = $this->ClampVertical($y - $value * $this->bar_unit_height);
    $bar['y'] = min($l1, $l2);
    $bar['height'] = abs($l1-$l2);
  }

  /**
   * Return box for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!array_key_exists($set, $this->bar_styles))
      return '';

    $bar = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h);
    return $this->Element('rect', $bar, $this->bar_styles[$set]);
  }

}

