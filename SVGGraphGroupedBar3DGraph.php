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

require_once 'SVGGraphMultiGraph.php';
require_once 'SVGGraphBar3DGraph.php';

class GroupedBar3DGraph extends Bar3DGraph {

  protected $multi_graph;

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $chunk_count = count($this->values);
    $gap_count = $chunk_count - 1;
    $bar_width = ($this->bar_space >= $this->bar_unit_width ? '1' : 
      $this->bar_unit_width - $this->bar_space);
    $chunk_gap = $gap_count > 0 ? $this->group_space : 0;
    if($gap_count > 0 && $chunk_gap * $gap_count > $bar_width - $chunk_count)
      $chunk_gap = ($bar_width - $chunk_count) / $gap_count;
    $chunk_width = ($bar_width - ($chunk_gap * ($chunk_count - 1)))
      / $chunk_count;
    $chunk_unit_width = $chunk_width + $chunk_gap;
    $bar_style = array();
    $this->SetStroke($bar_style);
    $bar = array('width' => $chunk_width);

    $this->block_width = $chunk_width;
    $bspace = $this->bar_space / 2;
    $b_start = $this->pad_left + $bspace;

    // make the top parallelogram, set it as a symbol for re-use
    list($this->bx, $this->by) = $this->Project(0, 0, $chunk_width);
    $top = $this->BarTop();

    $bnum = 0;
    $ccount = count($this->colours);
    $groups = array_fill(0, $chunk_count, '');

    // get the translation for the whole bar
    list($tx, $ty) = $this->Project(0, 0, $bspace);
    $group = array('transform' => "translate($tx,$ty)");

    $bars = '';
    foreach($this->multi_graph->all_keys as $k) {
      $bar_pos = $this->GridPosition($k, $bnum);
      if(!is_null($bar_pos)) {
        for($j = 0; $j < $chunk_count; ++$j) {
          $bar['x'] = $bspace + $bar_pos + ($j * $chunk_unit_width);
          $value = $this->multi_graph->GetValue($k, $j);

          if(!is_null($value)) {
            $colour = $j % $ccount;
            $bar_sections = $this->Bar3D($value, $bar, $top, $colour);
            $group['fill'] = $this->GetColour($colour);

            if($this->show_tooltips)
              $this->SetTooltip($group, $value);
            $link = $this->GetLink($k, $bar_sections);
            $bars .= $this->Element('g', $group, NULL, $link);
            unset($group['id']); // make sure a new one is generated
            $style = $group;
            $this->SetStroke($style);

            if(!array_key_exists($j, $this->bar_styles))
              $this->bar_styles[$j] = $style;
          }
        }
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
   * construct multigraph
   */
  public function Values($values)
  {
    parent::Values($values);
    $this->multi_graph = new MultiGraph($this->values, $this->force_assoc);
  }

  /**
   * Override AdjustAxes to change depth
   */
  protected function AdjustAxes(&$x_len, &$y_len)
  {
    /**
     * The depth is roughly 1/$num - but it must also take into account the
     * bar and group spacing, which is where things get messy
     */
    if($this->AssociativeKeys()) {
      $num = $this->GetHorizontalCount();
    } else {
      $ends = $this->GetAxisEnds();
      $num = max(0, $ends['k_max']) - min(0, $ends['k_min']) + 1;
    }
    $block = $x_len / $num;
    $group = count($this->values);
    $a = $this->bar_space;
    $b = $this->group_space;
    $c = (($block) - $a - ($group - 1) * $b) / $group;
    $d = ($a + $c) / $block;
    $this->depth = $d;
    return parent::AdjustAxes($x_len, $y_len);
  }

  /**
   * Find the full length
   */
  protected function GetHorizontalCount()
  {
    return $this->multi_graph->KeyCount();
  }

  /**
   * Returns the maximum value
   */
  protected function GetMaxValue()
  {
    return $this->multi_graph->GetMaxValue();
  }

  /**
   * Returns the minimum value
   */
  protected function GetMinValue()
  {
    return $this->multi_graph->GetMinValue();
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

  /**
   * Returns the key from the MultiGraph
   */
  protected function GetKey($index)
  {
    return $this->multi_graph->GetKey($index);
  }

  /**
   * Returns the maximum key from the MultiGraph
   */
  protected function GetMaxKey()
  {
    return $this->multi_graph->GetMaxKey();
  }

  /**
   * Returns the minimum key from the MultiGraph
   */
  protected function GetMinKey()
  {
    return $this->multi_graph->GetMinKey();
  }

}

