<?php
/**
 * Copyright (C) 2011-2012 Graham Breach
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

require_once 'SVGGraphGridGraph.php';

class HorizontalBarGraph extends GridGraph {

  protected $flip_axes = TRUE;
  protected $label_centre = TRUE;
  protected $bar_styles = array();

  protected function Draw()
  {
    $values = $this->GetValues();
    $assoc = $this->AssociativeKeys();
    $this->CalcAxes($assoc, true);
    $body = $this->Grid();

    $bar_height = ($this->bar_space >= $this->bar_unit_height ? '1' : 
      $this->bar_unit_height - $this->bar_space);
    $bar_style = array();
    $this->SetStroke($bar_style);

    $bnum = 0;
    $bspace = $this->bar_space / 2;
    $ccount = count($this->colours);
    foreach($values as $key => $value) {
      $bar = array('height' => $bar_height);
      $bar_pos = $this->GridPosition($key, $bnum);
      if(!is_null($bar_pos)) {
        $bar['y'] = $bar_pos - $bspace - $bar_height;
        $this->Bar($value, $bar);

        if($bar['width'] > 0) {
          $bar_style['fill'] = $this->GetColour($bnum % $ccount);

          if($this->show_tooltips)
            $this->SetTooltip($bar, $value);
          $rect = $this->Element('rect', $bar, $bar_style);
          $body .= $this->GetLink($key, $rect);
          $this->bar_styles[] = $bar_style;
        }
      }
      ++$bnum;
    }

    $body .= $this->Axes();
    return $body;
  }

  /**
   * Fills in the x-position and width of a bar
   */
  protected function Bar($value, &$bar)
  {
    $x0 = $this->pad_left + $this->x0;
    $l1 = $this->ClampHorizontal($x0);
    $l2 = $this->ClampHorizontal($x0 + ($value * $this->bar_unit_width));
    $bar['x'] = min($l1, $l2);
    $bar['width'] = abs($l1-$l2);
  }

  /**
   * Overload to measure keys
   */
  protected function LabelAdjustment($longest = 1000)
  {
    $longest_key = '';
    if($this->show_axis_text_v) {
      $max_len = 0;
      foreach($this->values[0] as $k => $v) {
        $len = strlen($k);
        if($len > $max_len) {
          $max_len = $len;
          $longest_key = $k;
        }
      }
    }
    parent::LabelAdjustment($longest_key);
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

