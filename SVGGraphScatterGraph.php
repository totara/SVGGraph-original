<?php
/**
 * Copyright (C) 2010-2012 Graham Breach
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

/**
 * ScatterGraph - points with axes and grid
 */
class ScatterGraph extends PointGraph {

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);
    $values = $this->GetValues();

    // a scatter graph without markers is empty!
    if($this->marker_size == 0)
      $this->marker_size = 1;

    $bnum = 0;
    foreach($values as $key => $value) {
      if($this->scatter_2d && is_array($value)) {
        $key = $value[0];
        $value = $value[1];
      }
      $point_pos = $this->GridPosition($key, $bnum);
      if(!is_null($value) && !is_null($point_pos)) {
        $x = $point_pos;
        $y = $this->height - $this->pad_bottom - $this->y0 
          - ($value * $this->bar_unit_height);

        $this->AddMarker($x, $y, $key, $value);
      }
      ++$bnum;
    }

    if($this->best_fit) {
      $best_fit = is_array($this->best_fit) ? $this->best_fit[0] :
        $this->best_fit;
      $colour = is_array($this->best_fit_colour) ? $this->best_fit_colour[0] :
        $this->best_fit_colour;
      $stroke_width = is_array($this->best_fit_width) ?
        $this->best_fit_width[0] : $this->best_fit_width;
      $dash = is_array($this->best_fit_dash) ?
        $this->best_fit_dash[0] : $this->best_fit_dash;
      $body .= $this->BestFit($best_fit, 0, $colour, $stroke_width, $dash);
    }
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE);
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
    return $body;
  }

  /**
   * Sets up values array
   */
  public function Values($values)
  {
    if(!$this->scatter_2d)
      return parent::Values($values);

    $this->values = array();
    $v = func_get_args();
    if(count($v) == 1)
      $v = $v[0];
    if(is_array($v) && isset($v[0]) && is_array($v[0]) && is_array($v[0][0]))
      $this->values = $v;
    elseif(is_array($v) && isset($v[0]) && is_array($v[0]))
      $this->values[0] = $v;
    else
      throw new Exception(
        'Scatter 2D mode requires array of array(x,y) points'
      );
  }

  /**
   * Checks that the data produces a 2-D plot
   */
  protected function CheckValues(&$values)
  {
    parent::CheckValues($values);

    // using force_assoc makes things work properly
    if($this->AssociativeKeys())
      $this->force_assoc = true;
  }

  /**
   * Overload GetMaxValue to support scatter_2d data
   */
  protected function GetMaxValue()
  {
    if(!$this->scatter_2d)
      return parent::GetMaxValue();

    return array_reduce($this->values[0], 'pointgraph_vmax', null);
  }

  /**
   * Overload GetMinValue to support scatter_2d data
   */
  protected function GetMinValue()
  {
    if(!$this->scatter_2d)
      return parent::GetMinValue();

    return array_reduce($this->values[0], 'pointgraph_vmin', null);
  }

  /**
   * Overload GetMaxKey to support scatter_2d data
   */
  protected function GetMaxKey()
  {
    if(!$this->scatter_2d)
      return parent::GetMaxKey();

    return array_reduce($this->values[0], 'pointgraph_kmax', null);
  }

  /**
   * Overload GetMinKey to support scatter_2d data
   */
  protected function GetMinKey()
  {
    if(!$this->scatter_2d)
      return parent::GetMinKey();

    return array_reduce($this->values[0], 'pointgraph_kmin', null);
  }

}

