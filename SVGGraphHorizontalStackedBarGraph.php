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

require_once('SVGGraphMultiGraph.php');
require_once('SVGGraphHorizontalBarGraph.php');

class HorizontalStackedBarGraph extends HorizontalBarGraph {

	protected $multi_graph;
	protected $bar_space = 10;
	protected $label_centre = true;
	protected $flip_axes = true;

	protected function Draw()
	{
		$assoc = $this->AssociativeKeys();
		$this->CalcAxes($assoc,true);
		$body = $this->Grid();

		$bar_height = ($this->bar_space >= $this->bar_unit_height ? '1' : 
			$this->bar_unit_height - $this->bar_space);
		$bar_style = array();
		$this->SetStroke($bar_style);
		$bar = array('height' => $bar_height);

		$bnum = 0;
		$bspace = $this->bar_space / 2;
		$b_start = $this->height - $this->pad_bottom - ($this->bar_space / 2);
		$ccount = count($this->colours);
		$chunk_count = count($this->values);
		foreach($this->multi_graph->all_keys as $k) {
			$bar_pos = $this->GridPosition($k, $bnum);
			if(!is_null($bar_pos)) {
				$bar['y'] = $bar_pos - $bspace - $bar_height;

				$xpos = $xneg = $this->pad_left + $this->x0;
				$xpos = $xneg = $xplus = $xminus = 0;
				for($j = 0; $j < $chunk_count; ++$j) {
					$value = $this->multi_graph->GetValue($k, $j);
					$this->Bar($value >= 0 ? $value + $xplus : $value - $xminus, $bar);
					if($value < 0) {
						$bar['width'] -= $xneg;
						$xneg += $bar['width'];
						$xminus -= $value;
					} else {
						$bar['width'] -= $xpos;
						$bar['x'] += $xpos;
						$xpos += $bar['width'];
						$xplus += $value;
					}

					if($bar['width'] > 0) {
						$bar_style['fill'] = $this->GetColour($j % $ccount);

						if($this->show_tooltips)
							$this->SetTooltip($bar, $value);
						$rect = $this->Element('rect', $bar, $bar_style);
						$body .= $this->GetLink($k, $rect);
						unset($bar['id']); // clear ID for next generated value
					}
				}
			}
			++$bnum;
		}

		$body .= $this->Axes();
		return $body;
	}

	/**
	 * construct multigraph
	 */
	public function Values($values)
	{
		parent::Values($values);
		$this->multi_graph = new MultiGraph($this->values);
	}

	/**
	 * Find the longest data set
	 */
	protected function GetHorizontalCount()
	{
		return $this->multi_graph->KeyCount();
	}

	/**
	 * Returns the maximum (stacked) value
	 */
	protected function GetMaxValue()
	{
		$stack = array();
		$chunk_count = count($this->values);

		foreach($this->multi_graph->all_keys as $k) {
			$s = 0;
			for($j = 0; $j < $chunk_count; ++$j) {
				$v = $this->multi_graph->GetValue($k, $j);
				if($v > 0)
					$s += $v;
			}
			$stack[] = $s;
		}
		return max($stack);
	}

	/**
	 * Returns the minimum (stacked) value
	 */
	protected function GetMinValue()
	{
		$stack = array();
		$chunk_count = count($this->values);

		foreach($this->multi_graph->all_keys as $k) {
			$s = 0;
			for($j = 0; $j < $chunk_count; ++$j) {
				$v = $this->multi_graph->GetValue($k, $j);
				if($v <= 0)
					$s += $v;
			}
			$stack[] = $s;
		}
		return min($stack);
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

	/**
	 * Overload to measure keys
	 */
	protected function LabelAdjustment($m = 1000)
	{
		if($this->show_label_v) {
			$max_len = 0;
			foreach($this->multi_graph->all_keys as $k) {
				$len = strlen($k);
				if($len > $max_len)
					$max_len = $len;
			}

			$this->pad_left += $this->axis_font_size * $max_len * $this->axis_font_adjust;
		}
		if($this->show_label_h)
			$this->pad_bottom += $this->axis_font_size;
		$this->label_adjust_done = true;
	}
}

