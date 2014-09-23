<?php
/**
 * Copyright (C) 2009-2011 Graham Breach
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

abstract class GridGraph extends Graph {
	protected $grid_colour = 'rgb(220,220,220)';
	protected $axis_colour = 'rgb(0,0,0)';
	protected $label_colour = 'rgb(0,0,0)';
	protected $show_grid = true;
	protected $show_axes = true;
	protected $show_label_h = true;
	protected $show_label_v = true;
	protected $axis_font = 'monospace';
	protected $axis_font_size = '10'; // pixels
	protected $axis_font_adjust = 0.6; // approx ratio of width to height
	protected $axis_overlap = 5;
	protected $show_divisions = true;
	protected $division_size = 3;
	protected $division_colour = NULL; // default to use axis colour
	protected $minimum_grid_spacing = 15;
	protected $minimum_grid_spacing_h = null;
	protected $minimum_grid_spacing_v = null;
	protected $axis_min_h = null;
	protected $axis_min_v = null;
	protected $axis_max_h = null;
	protected $axis_max_v = null;
	protected $grid_division_h = null;
	protected $grid_division_v = null;

	protected $bar_unit_width = 0;
	protected $x0;
	protected $y0;
	protected $y_points;
	protected $x_points;
	protected $flip_axes = false;

	// set to true for block-based labelling
	protected $label_centre = false;

	protected $g_width = null;
	protected $g_height = null;
	protected $uneven_x = false;
	protected $uneven_y = false;
	protected $label_adjust_done = false;
	protected $axes_calc_done = false;

	protected function LabelAdjustment($m = 1000)
	{
		// make a guess at length of numbers
		$len = strlen($this->NumString($m)) + 1;

		// make space for labels
		if($this->show_label_v)
			$this->pad_left += $this->axis_font_size * $len * $this->axis_font_adjust;
		if($this->show_label_h)
			$this->pad_bottom += $this->axis_font_size;
		$this->label_adjust_done = true;
	}

	/**
	 * Calculates the effect of axes, applying to padding
	 *  h_by_count = use the number of values instead of min/max
	 *  bar        = bar graph mode (0 to right of origin)
	 */
	protected function CalcAxes($h_by_count = false, $bar = false)
	{
		if($this->axes_calc_done)
			return;

		$v_max = $this->GetMaxValue();
		$v_min = $this->GetMinValue();
		$k_count = $this->GetHorizontalCount();
		$k_max = $this->GetMaxKey();
		$k_min = $this->GetMinKey();

		// validate axes
		if((!is_null($this->axis_max_h) && !is_null($this->axis_min_h) && $this->axis_max_h <= $this->axis_min_h) ||
			(!is_null($this->axis_max_v) && !is_null($this->axis_min_v) && $this->axis_max_v <= $this->axis_min_v))
			throw new Exception('Invalid axes specified');
		if((!is_null($this->axis_max_h) && ($this->axis_max_h < ($this->flip_axes ? $v_min : $k_min))) ||
			(!is_null($this->axis_min_h) && ($this->axis_min_h >= ($this->flip_axes ? $v_max : $k_max+1))) ||
			(!is_null($this->axis_max_v) && ($this->axis_max_v < ($this->flip_axes ? $k_min : $v_min))) ||
			(!is_null($this->axis_min_v) && ($this->axis_min_v >= ($this->flip_axes ? $k_max+1 : $v_max))))
				throw new Exception('No values in grid range');

		// if fixed grid spacing is specified, make the min spacing 1 pixel
		if(!is_null($this->grid_division_v))
			$this->minimum_grid_spacing_v = 1;
		if(!is_null($this->grid_division_h))
			$this->minimum_grid_spacing_h = 1;

		if(!$this->label_adjust_done)
			$this->LabelAdjustment($v_max);

		if(is_null($this->g_height))
			$this->g_height = $this->height - $this->pad_top - $this->pad_bottom;
		if(is_null($this->g_width))
			$this->g_width = $this->width - $this->pad_left - $this->pad_right;

		$x_max = $h_by_count ? $k_count - 1 : max(0, $k_max);
		$x_min = $h_by_count ? 0 : min(0, $k_min);
		$x_len = $this->g_width;
		$y_len = $this->g_height;
		$bar_h = $bar_v = null;
		if($this->flip_axes) {

			if(is_null($this->grid_division_h)) {
				$x_axis = new Axis($x_len,
					is_null($this->axis_max_h) ? max(0, $v_max) : $this->axis_max_h,
					is_null($this->axis_min_h) ? min(0, $v_min) : $this->axis_min_h);
			} else {
				$x_axis = new AxisFixed($x_len,
					is_null($this->axis_max_h) ? max(0, $v_max) : $this->axis_max_h,
					is_null($this->axis_min_h) ? min(0, $v_min) : $this->axis_min_h,
					$this->grid_division_h);
			}

			$y_axis = new Axis($y_len,
				is_null($this->axis_max_v) ? $x_max : $this->axis_max_v,
				is_null($this->axis_min_v) ? $x_min : $this->axis_min_v,
				1, true);
			$bar_v = $bar;

		} else {

			$x_axis = new Axis($x_len,
				is_null($this->axis_max_h) ? $x_max : $this->axis_max_h,
				is_null($this->axis_min_h) ? $x_min : $this->axis_min_h,
				1, true);
	
			if(is_null($this->grid_division_v)) {
				$y_axis = new Axis($y_len,
					is_null($this->axis_max_v) ? max(0, $v_max) : $this->axis_max_v,
					is_null($this->axis_min_v) ? min(0, $v_min) : $this->axis_min_v);
			} else {
				$y_axis = new AxisFixed($y_len,
					is_null($this->axis_max_v) ? max(0, $v_max) : $this->axis_max_v,
					is_null($this->axis_min_v) ? min(0, $v_min) : $this->axis_min_v,
					$this->grid_division_v);
			}
			$bar_h = $bar;
		}

		if(is_null($this->minimum_grid_spacing_h))
			$this->minimum_grid_spacing_h = $this->minimum_grid_spacing;
		if(is_null($this->minimum_grid_spacing_v))
			$this->minimum_grid_spacing_v = $this->minimum_grid_spacing;
		$this->h_grid = $x_axis->Grid($this->minimum_grid_spacing_h, $bar_h);
		$this->v_grid = $y_axis->Grid($this->minimum_grid_spacing_v, $bar_v);

		$this->x0 = $x_axis->Zero();
		$this->y0 = $y_axis->Zero();
		$this->uneven_x = $x_axis->Uneven();
		$this->uneven_y = $y_axis->Uneven();

		$this->bar_unit_width = $x_axis->Unit();
		$this->bar_unit_height = $y_axis->Unit();

		$this->axis_width = $this->g_width;
		$this->axis_height = $this->g_height;

		$this->axes_calc_done = true;
	}

	/**
	 * Calculates the position of grid lines
	 */
	protected function CalcGrid()
	{
		if(isset($this->y_points))
			return;

		$grid_bottom = $this->height - $this->pad_bottom;
		$grid_top = $this->pad_top;
		$grid_left = $this->pad_left;
		$grid_right = $this->width - $this->pad_right;
		$this->y_points = array();
		$this->x_points = array();

		// keys are converted to strings to make them work
		$c = $y = 0;
		$yd = $this->v_grid / 2.0;

		while($y < $this->axis_height + $yd) {
			$ypoint = $this->NumString(($y - $this->y0) / $this->bar_unit_height);
			$this->y_points[$ypoint] = $grid_bottom - $y;
			++$c;
			$y = $c * $this->v_grid;
		} 

		$c = $x = 0;
		$xd = $this->h_grid / 2.0;
		while($x < $this->axis_width + $xd) {
			$xpoint = $this->NumString(($x - $this->x0) / $this->bar_unit_width);
			$this->x_points[$xpoint] = $grid_left + $x;
			++$c;
			$x = $c * $this->h_grid;
		} 
		// prime numbers can cause trouble
		if($this->uneven_x) {
			$x = $grid_right - $grid_left - $this->x0;
			$this->x_points[$this->NumString($x / $this->bar_unit_width)] = $grid_right;
		}
		if($this->uneven_y) {
			$y = $grid_bottom - $grid_top - $this->y0;
			$this->y_points[$this->NumString($y / $this->bar_unit_height)] = $grid_top;
		}
	}

	/**
	 * Converts number to string
	 */
	protected function NumString($n)
	{
		return is_int($n) ? number_format($n) : (string)($n);
	}

	/**
	 * Subclasses can override this for non-linear graphs
	 */
	protected function GetHorizontalCount()
	{
		$values = $this->GetValues();
		return count($values);
	}

	/**
	 * Draws bar or line graph axes
	 */
	protected function Axes()
	{
		if(!$this->show_axes)
			return '';

		$x_axis_visible = $this->y0 >= 0 && $this->y0 < $this->g_height;
		$y_axis_visible = $this->x0 >= 0 && $this->x0 < $this->g_width;
		$yoff = $x_axis_visible ? $this->y0 : 0;
		$xoff = $y_axis_visible ? $this->x0 : 0;

		$points = array();
		$axis_group = $x_axis = $y_axis = '';
		if($x_axis_visible) {
			$points['x1'] = $this->pad_left - $this->axis_overlap;
			$points['x2'] = $this->width - $this->pad_right + $this->axis_overlap;
			$points['y1'] = $points['y2'] = $this->height - $this->pad_bottom - $yoff;
			$x_axis = $this->Element('line', $points);
		}

		if($y_axis_visible) {
			$points['x1'] = $points['x2'] = $this->pad_left + $xoff;
			$points['y1'] = $this->pad_top - $this->axis_overlap;
			$points['y2'] = $this->height - $this->pad_bottom + $this->axis_overlap;
			$y_axis = $this->Element('line', $points);
		}

		if($x_axis != '' || $y_axis != '') {
			$line = array('stroke-width' => 2, 'stroke' => $this->axis_colour);
			$axis_group = $this->Element('g', $line, NULL, $x_axis . $y_axis);
		}

		$label_group = '';
		$divisions = '';

		$grid_bottom = $this->height - $this->pad_bottom;
		$grid_top = $this->pad_top; // or $grid_bottom - $this->axis_height ?
		$grid_left = $this->pad_left;
		$grid_right = $this->width - $this->pad_right;

		$this->CalcGrid();
		$path = '';

		if($this->show_label_v || $this->show_label_h || $this->show_divisions) {
			$text = array('x' => $this->pad_left - $this->axis_overlap);
	
			$x_offset = $y_offset = 0;
			$label_centre_x = $this->label_centre && !$this->flip_axes;
			$label_centre_y = $this->label_centre && $this->flip_axes;
			if($this->label_centre) {
				if($this->flip_axes)
					$y_offset = -0.5 * $this->bar_unit_height;
				else
					$x_offset = 0.5 * $this->bar_unit_width;
			}

			$v_group = '';
			$y_prev = $this->height;
			arsort($this->y_points);
			if($this->show_label_v || $this->show_divisions) {
				$labels = '';
				$text_centre = $this->axis_font_size * 0.3;
				$d_path = '';

				$points = count($this->y_points);
				$p = 0;
				foreach($this->y_points as $label => $y) {

					$text['y'] = $y + $text_centre + $y_offset;
					$key = $this->flip_axes ? $this->GetKey($label) : $label;

					if($y_prev - $y >= $this->minimum_grid_spacing_v && (++$p < $points || !$label_centre_y))
						$labels .= $this->Element('text', $text, NULL, $key);
					$d_path .= 'M' . ($grid_left + $xoff) . ' ' . $y . 'l-' . $this->division_size . ' 0';
					$y_prev = $y;
				}
				$v_group = $this->Element('g', array('text-anchor' => 'end'), NULL, $labels);
			}

			$h_group = '';
			$x_prev = -$this->width;
			asort($this->x_points);
			if($this->show_label_h || $this->show_divisions) {
				$labels = '';
				$text['y'] = $this->height - $this->pad_bottom + $this->axis_font_size;
				$w = $this->width - $this->pad_left - $this->pad_right;

				$points = count($this->x_points);
				$p = 0;
				foreach($this->x_points as $label => $x) {

					$text['x'] = $x + $x_offset;
					$key = $this->flip_axes ? $label : $this->GetKey($label);
					if($x - $x_prev >= $this->minimum_grid_spacing_h && (++$p < $points || !$label_centre_x))
						$labels .= $this->Element('text', $text, NULL, $key);
						
					$d_path .= 'M' . $x . ' ' . ($grid_bottom - $yoff) . 'l0 ' . $this->division_size;
					$x_prev = $x;
				}
				$h_group = $this->Element('g', array('text-anchor' => 'middle'), NULL, $labels);
			}

			$font = array(
				'font-size' => $this->axis_font_size,
				'font-family' => $this->axis_font,
				'fill' => $this->label_colour,
			);
			if($this->show_label_h || $this->show_label_v) {
				$label_group = $this->Element('g', $font, NULL, 
					($this->show_label_h ? $h_group : '') .
					($this->show_label_v ? $v_group : ''));
			}

			if($this->show_divisions) {
				$colour = is_null($this->division_colour) ? $this->axis_colour : $this->division_colour;
				$div = array('d' => $d_path, 'stroke-width' => 1, 'stroke' => $colour);
				$divisions = $this->Element('path', $div);
			}
		}
		return $divisions . $axis_group . $label_group;
	}

	/**
	 * Draws the grid behind the bar / line graph
	 */
	protected function Grid()
	{
		$this->CalcAxes();
		if(!$this->show_grid)
			return '';

		$x1 = $this->pad_left;
		$x2 = $this->width - $this->pad_right;
		$y1 = $this->height - $this->pad_bottom;
		$y2 = $this->pad_top;

		$this->CalcGrid();
		$path = '';
		foreach($this->y_points as $y) 
			$path .= 'M' . $x1 . ' ' . $y . 'L' . $x2 . ' ' . $y;
		foreach($this->x_points as $x) 
			$path .= 'M' . $x . ' ' . $y1 . 'L' . $x . ' ' . $y2;

		$opts = array('d' => $path, 'stroke' => $this->grid_colour);
		return $this->Element('path', $opts);
	}

	/**
	 * clamps a value to the grid boundaries
	 */
	protected function ClampVertical($val)
	{
		return max($this->pad_top, min($this->height - $this->pad_bottom, $val));
	}

	protected function ClampHorizontal($val)
	{
		return max($this->pad_left, min($this->width - $this->pad_right, $val));
	}

	/**
	 * Returns a clipping path for the grid
	 */
	protected function ClipGrid(&$attr)
	{
		$rect = array(
			'x' => $this->pad_left, 'y' => $this->pad_top,
			'width' => $this->width - $this->pad_left - $this->pad_right,
			'height' => $this->height - $this->pad_top - $this->pad_bottom
		);
		$this->defs[] = $this->Element('clipPath', array('id' => 'clipGrid'), NULL,
			$this->Element('rect', $rect));
		$attr['clip-path'] = 'url(#clipGrid)';
	}
}

