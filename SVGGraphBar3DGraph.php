<?php
/**
 * Copyright (C) 2009-2013 Graham Breach
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

  protected $label_centre = true;
  protected $bar_styles = array();
  protected $bx;
  protected $by;
  protected $block_width;

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);
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
    foreach($this->values[0] as $item) {
      $bar_pos = $this->GridPosition($item->key, $bnum);

      if($this->legend_show_empty || !is_null($item->value)) {
        $bar_style = array('fill' => $this->GetColour($item, $bnum % $ccount));
        $this->SetStroke($bar_style, $item);
      } else {
        $bar_style = NULL;
      }
      $this->bar_styles[] = $bar_style;

      if(!is_null($item->value) && !is_null($bar_pos)) {
        $bar['x'] = $bspace + $bar_pos;

        $bar_sections = $this->Bar3D($item, $bar, $top, $bnum % $ccount);
        if($bar_sections != '') {
          $link = $this->GetLink($item, $item->key, $bar_sections);

          $group = array_merge($group, $bar_style);
          if($this->show_tooltips)
            $this->SetTooltip($group, $item, $item->value);
          $this->SetStroke($group, $item, 0, 'round');
          $bars .= $this->Element('g', $group, NULL, $link);
          unset($group['id']); // make sure a new one is generated
        }
      }
      ++$bnum;
    }

    $body .= $bars;
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
  protected function Bar3D($item, &$bar, &$top, $colour, $start = null)
  {
    $pos = $this->Bar($item->value, $bar, $start);
    if(is_null($pos) || $pos > $this->height - $this->pad_bottom)
      return '';

    $side_x = $bar['x'] + $this->block_width;
    $side = array(
      'd' => "M0,0 l{$this->bx},{$this->by} l0,$bar[height] l-{$this->bx}," . -$this->by . " z",
      'transform' => "translate($side_x,$bar[y])"
    );
    if(is_null($top)) {
      $bar_top = '';
    } else {
      $top['transform'] = "translate($bar[x],$bar[y])";
      $top['fill'] = $this->GetColour($item, $colour, TRUE);
      $bar_top = $this->Element('use', $top, null, $this->empty_use ? '' : null);
    }

    $rect = $this->Element('rect', $bar);
    $bar_side = $this->Element('path', $side);
    return $rect . $bar_top . $bar_side;
  }

  /**
   * Fills in the y-position and height of a bar (copied from BarGraph)
   * @param number $value bar value
   * @param array  &$bar  bar element array [out]
   * @param number $start bar start value
   * @return number unclamped bar position
   */
  protected function Bar($value, &$bar, $start = null)
  {
    if($start)
      $value += $start;

    $startpos = is_null($start) ? $this->OriginY() : $this->GridY($start);
    $pos = $this->GridY($value);
    if(is_null($pos)) {
      $bar['height'] = 0;
    } else {
      $l1 = $this->ClampVertical($startpos);
      $l2 = $this->ClampVertical($pos);
      $bar['y'] = min($l1, $l2);
      $bar['height'] = abs($l1-$l2);
    }
    return $pos;
  }

  /**
   * Return box for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!isset($this->bar_styles[$set]))
      return '';

    $bar = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h);
    return $this->Element('rect', $bar, $this->bar_styles[$set]);
  }

}

