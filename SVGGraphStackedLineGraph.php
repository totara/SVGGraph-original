<?php
/**
 * Copyright (C) 2012 Graham Breach
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

require_once 'SVGGraphPointGraph.php';
require_once 'SVGGraphMultiGraph.php';
require_once 'SVGGraphMultiLineGraph.php';

/**
 * StackedLineGraph - multiple joined lines with values added together
 */
class StackedLineGraph extends MultiLineGraph {

  protected $legend_reverse = true;

  public function Draw()
  {
    $assoc = $this->AssociativeKeys();
    $this->CalcAxes($assoc);
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $plots = array();
    $y_axis_pos = $this->height - $this->pad_bottom - $this->y0;
    $y_bottom = min($y_axis_pos, $this->height - $this->pad_bottom);

    $ccount = count($this->colours);
    $chunk_count = count($this->values);
    if(!$assoc)
      sort($this->multi_graph->all_keys, SORT_NUMERIC);
    $stack = array();
    for($i = 0; $i < $chunk_count; ++$i) {
      $bnum = 0;
      $cmd = 'M';
      $path = '';
      $attr = array('fill' => 'none');
      $fill = $this->multi_graph->Option($this->fill_under, $i);
      $dash = $this->multi_graph->Option($this->line_dash, $i);
      $stroke_width = 
        $this->multi_graph->Option($this->line_stroke_width, $i);
      if($fill) {
        $cmd = 'L';
        $attr['fill'] = $this->GetColour($i % $ccount);
        $attr['fill-opacity'] = 
          $this->multi_graph->Option($this->fill_opacity, $i);
      }
      if(!is_null($dash))
        $attr['stroke-dasharray'] = $dash;
      $attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;


      $bottom = array();
      foreach($this->multi_graph->all_keys as $key) {
        $value = $this->multi_graph->GetValue($key, $i);
        $point_pos = $this->GridPosition($key, $bnum);
        if(!isset($stack[$key]))
          $stack[$key] = 0;
        if(!is_null($point_pos)) {
          $bottom[$point_pos] = $stack[$key];
          $x = $point_pos;
          $y_size = ($stack[$key] + $value) * $this->bar_unit_height;
          $y = $y_axis_pos - $y_size;
          $stack[$key] += $value;

          if($fill && $path == '')
            $path = "M$x $y_bottom";
          $path .= "$cmd$x $y ";

          // no need to repeat same L command
          $cmd = $cmd == 'M' ? 'L' : '';
          $this->AddMarker($x, $y, $key, $value, NULL, $i);
        }
        ++$bnum;
      }

      if($fill) {
        $bpoints = array_reverse($bottom, TRUE);
        foreach($bpoints as $x => $pos) {
          $y = $y_axis_pos - ($pos * $this->bar_unit_height);
          $path .= "$x $y ";
        }
      }

      $attr['d'] = $path;
      $attr['stroke'] = $this->GetColour($i % $ccount, true);
      $plots[] = $this->Element('path', $attr);
      unset($attr['d']);
      $this->AddLineStyle($attr);
    }

    $group = array();
    $this->ClipGrid($group);

    $plots = array_reverse($plots);
    $body .= $this->Element('g', $group, NULL, implode($plots));
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE);
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
    return $body;
  }


  /**
   * Returns the maximum value
   */
  protected function GetMaxValue()
  {
    return $this->multi_graph->GetMaxSumValue();
  }

  /**
   * Returns the minimum value
   */
  protected function GetMinValue()
  {
    return $this->multi_graph->GetMinSumValue();
  }

}

