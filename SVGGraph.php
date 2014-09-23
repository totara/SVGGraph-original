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

define('SVGGRAPH_VERSION', 'SVGGraph 2.6');

class SVGGraph {

  private $width = 100;
  private $height = 100;
  private $settings = array();
  public $values = array();
  public $links = NULL;
  public $colours = NULL;

  public function __construct($w, $h, $settings = NULL)
  {
    $this->width = $w;
    $this->height = $h;
    if(is_array($settings))
      $this->settings = $settings;
  }

  public function Values($values)
  {
    if(is_array($values)) 
      $this->values = $values;
    else
      $this->values = func_get_args();
  }
  public function Links($links)
  {
    $this->links = func_get_args();
  }
  public function Colours($colours)
  {
    $this->colours = func_get_args();
  }


  /**
   * Instantiate the correct class
   */
  private function Setup($class)
  {
    // load the relevant class file
    if(!class_exists($class))
      include 'SVGGraph' . $class . '.php';

    $g = new $class($this->width, $this->height, $this->settings);
    $g->Values($this->values);
    $g->Links($this->links);
    if(!is_null($this->colours))
      $g->colours = $this->colours;
    return $g;
  }

  /**
   * Fetch the content
   */
  public function Fetch($class, $header = TRUE)
  {
    $g = $this->Setup($class);
    return $g->Fetch($header);
  }

  /**
   * Pass in the type of graph to display
   */
  public function Render($class, $header = TRUE, $content_type = TRUE)
  {
    $g = $this->Setup($class);
    return $g->Render($header, $content_type);
  }
}

/**
 * Base class for all graph types
 */
abstract class Graph {

  protected $settings = array();
  protected $values = array();
  protected $link_base = '';
  protected $link_target = '_blank';
  protected $links = array();

  protected $defs = array();
  protected $javascript = NULL;

  protected $namespaces = array();

  public function __construct($w, $h, $settings = NULL)
  {
    $this->width = $w;
    $this->height = $h;

    // get settings from ini file that are relevant to this class
    $ini_settings = @parse_ini_file('svggraph.ini', TRUE);
    if($ini_settings === false)
      die('svggraph.ini file not found -- exiting');

    $class = get_class($this);
    $hierarchy = array($class);
    while($class = get_parent_class($class))
      array_unshift($hierarchy, $class);

    while(count($hierarchy)) {
      $class = array_shift($hierarchy);
      if(array_key_exists($class, $ini_settings))
        $this->settings = array_merge($this->settings, $ini_settings[$class]);
    }

    if(is_array($settings))
      $this->Settings($settings);

    // set default colours
    $this->colours = explode(' ', $this->svg_colours);
    shuffle($this->colours);
    unset($this->svg_colours);
  }


  /**
   * Retrieves properties from the settings array if they are not
   * already available as properties
   */
  public function __get($name)
  {
    if(isset($this->settings[$name]))
      return $this->settings[$name];

    return NULL;
  }


  /**
   * Sets the options
   */
  public function Settings(&$settings)
  {
    foreach($settings as $key => $value) {
      $this->settings[$key] = $value;
      $this->{$key} = $value;
    }
  }

  /**
   * Sets the graph values
   */
  public function Values($values)
  {
    $this->values = array();
    $v = func_get_args();
    if(count($v) == 1)
      $v = $v[0];
    if(is_array($v) && isset($v[0]) && is_array($v[0]))
      $this->values = $v;
    else
      $this->values[0] = $v;
  }

  /**
   * Returns a row of values
   */
  protected function GetValues($row = 0)
  {
    if(is_array($this->values[$row]))
      return $this->values[$row];
    
    return $this->values;
  }

  /**
   * Returns the key value for an index, if associative
   */
  protected function GetKey($index)
  {
    $k = array_keys($this->values[0]);

    // this works around a strange bug - if you just return the key at $index,
    // for a non-associative array it repeats some!
    if(is_int($k[0]))
      return $index;
    if(isset($k[$index])) {
      $index = (string)$index;
      $index = (int)$index;
      return $k[$index];
    }
    return NULL;
  }

  /**
   * Returns the minimum value
   */
  protected function GetMinValue()
  {
    if(is_array($this->values[0]))
      return min($this->values[0]);
    return min($this->values);
  }

  /**
   * Returns the maximum value
   */
  protected function GetMaxValue()
  {
    if(is_array($this->values[0]))
      return max($this->values[0]);
    return max($this->values);
  }

  /**
   * Returns the maximum key value
   */
  protected function GetMaxKey()
  {
    $k0 = $this->GetKey(0);
    if(is_numeric($k0))
      return max(array_keys($this->values[0]));

    // if associative, return the index of the last key
    return $this->GetHorizontalCount() - 1;
  }

  /**
   * Returns the minimum key value
   */
  protected function GetMinKey()
  {
    $k0 = $this->GetKey(0);
    if(is_numeric($k0))
      return min(array_keys($this->values[0]));
    return 0;
  }

  /**
   * Sets the links from each item
   */
  public function Links()
  {
    $args = func_get_args();
    $this->links = (is_array($args[0]) ? $args[0] : $args);
  }

  /**
   * Draws the selected graph
   */
  public function DrawGraph()
  {
    $group = array('clip-path' => "url(#canvas)");

    $contents = $this->Canvas();
    $contents .= $this->DrawTitle();
    $contents .= $this->Draw();
    $contents .= $this->DrawLegend();

    $body = $this->Element('g', $group, NULL, $contents);
    return $body;
  }


  /**
   * Draws the legend
   */
  protected function DrawLegend()
  {
    if(empty($this->legend_entries))
      return '';

    $text_parts = $entry_parts = $title = '';
    $longest = $title_width = $entries_x = 0;

    $y = $this->legend_padding;
    $w = $this->legend_entry_width;
    $x = 0;
    $entry_height = max($this->legend_font_size, $this->legend_entry_height);
    $text_y_offset = $entry_height / 2 + $this->legend_font_size / 2;

    // make room for title
    if($this->legend_title != '') {
      if(empty($this->legend_title_font_size))
        $this->legend_title_font_size = $this->legend_font_size;
      if(empty($this->legend_title_font_adjust))
        $this->legend_title_font_adjust = $this->legend_font_adjust;

      $y += $this->legend_title_font_size + $this->legend_padding;
      $title_width = $this->legend_padding * 2 +
        $this->legend_title_font_size * $this->legend_title_font_adjust *
        strlen($this->legend_title);
    }

    $text = array('x' => 0);
    foreach($this->legend_entries as $key => $value) {
      if(!empty($value)) {
        $entry = $this->DrawLegendEntry($key, $x, $y, $w, $entry_height);
        if(!empty($entry)) {
          if(strlen($value) > $longest)
            $longest = strlen($value);
          $text['y'] = $y + $text_y_offset;
          $text_parts .= $this->Element('text', $text, NULL, $value);
          $entry_parts .= $entry;
          $y += $entry_height + $this->legend_padding;
        }
      }
    }
    // if there's nothing to go in the legend, stop now
    if($entry_parts == '')
      return;

    $text_space = $longest * $this->legend_font_size * 
      $this->legend_font_adjust;
    if($this->legend_text_side == 'left') {
      $text_x_offset = $text_space + $this->legend_padding;
      $entries_x_offset = $text_space + $this->legend_padding * 2;
    } else {
      $text_x_offset = $w + $this->legend_padding * 2;
      $entries_x_offset = $this->legend_padding;
    }
    $longest_width = $this->legend_padding * 3 +
      $this->legend_entry_width + $text_space;
    $width = max($title_width, $longest_width);
    $height = $y;

    // centre the entries if the title makes the box bigger
    if($width > $longest_width) {
      $offset = ($width - $longest_width) / 2;
      $entries_x_offset += $offset;
      $text_x_offset += $offset;
    }

    $text_group = array('transform' => "translate($text_x_offset,0)");
    if($this->legend_text_side == 'left')
      $text_group['text-anchor'] = 'end';
    $entries_group = array('transform' => "translate($entries_x_offset,0)");
    $parts = $this->Element('g', $entries_group, NULL, $entry_parts) .
      $this->Element('g', $text_group, NULL, $text_parts);

    // create box and title
    $box = array(
      'fill' => $this->legend_back_colour,
      'rx' => $this->legend_round, 'ry' => $this->legend_round,
      'width' => $width,
      'height' => $height,
    );
    if($this->legend_stroke_width) {
      $box['stroke-width'] = $this->legend_stroke_width;
      $box['stroke'] = $this->legend_stroke_colour;
    }
    $rect = $this->Element('rect', $box);
    if($this->legend_title != '') {
      $text['x'] = $width / 2;
      $text['y'] = $this->legend_padding + $this->legend_title_font_size;
      $text['text-anchor'] = 'middle';
      if(!empty($this->legend_title_font) &&
        $this->legend_title_font != $this->legend_font)
        $text['font-family'] = $this->legend_title_font;
      if($this->legend_title_font_size != $this->legend_font_size)
        $text['font-size'] = $this->legend_title_font_size;
      if($this->legend_title_font_weight != $this->legend_font_weight)
        $text['font-weight'] = $this->legend_title_font_weight;
      if(!empty($this->legend_title_colour) &&
        $this->legend_title_colour != $this->legend_colour)
        $text['fill'] = $this->legend_title_colour;
      $title = $this->Element('text', $text, NULL, $this->legend_title);
    }

    // create group to contain whole legend
    list($left, $top) = $this->ParsePosition($this->legend_position,
      $width, $height);
    $group = array(
      'font-family' => $this->legend_font,
      'font-size' => $this->legend_font_size,
      'fill' => $this->legend_colour,
      'transform' => "translate($left,$top)",
    );
    if($this->legend_font_weight != 'normal')
      $group['font-weight'] = $this->legend_font_weight;


    if($this->legend_autohide)
      $this->AutoHide($group);
    if($this->legend_draggable)
      $this->SetDraggable($group);
    return $this->Element('g', $group, NULL, $rect . $title . $parts);
  }

  /**
   * Parses a position string, returning x and y coordinates
   */
  protected function ParsePosition($pos, $w = 0, $h = 0, $pad = 0)
  {
    $offset_x = $offset_y = 0;
    $inner = $top = $left = true;
    $parts = preg_split('/\s+/', $pos);
    while(count($parts)) {
      $part = array_shift($parts);
      switch($part) {
      case 'outer' : $inner = false;
        break;
      case 'inner' : $inner = true;
        break;
      case 'top' : $top = true;
        break;
      case 'bottom' : $top = false;
        break;
      case 'left' : $left = true;
        break;
      case 'right' : $left = false;
        break;
      default:
        if(is_numeric($part)) {
          $offset_x = $part;
          if(count($parts) && is_numeric($parts[0]))
            $offset_y = array_shift($parts);
        }
      }
    }
  
    if($top)
      $y = $pad + $offset_y + ($inner ? $this->pad_top : 0);
    else
      $y = $this->height - $h - ($inner ? $this->pad_bottom : 0)
        - $pad + $offset_y;

    if($left)
      $x = $pad + $offset_x + ($inner ? $this->pad_left : 0);
    else
      $x = $this->width - $w - ($inner ? $this->pad_right : 0)
        - $pad + $offset_x;

    return array($x,$y);
  }

  /**
   * Subclasses must draw the entry, if they can
   */
  protected function DrawLegendEntry($key, $x, $y, $w, $h)
  {
    return '';
  }

  /**
   * Draws the graph title, if there is one
   */
  protected function DrawTitle()
  {
    // graph_title is available for all graph types
    if(strlen($this->graph_title) <= 0)
      return '';

    $pos = $this->graph_title_position;
    $text = array(
      'font-size' => $this->graph_title_font_size,
      'font-family' => $this->graph_title_font,
      'font-weight' => $this->graph_title_font_weight,
      'text-anchor' => 'middle',
      'fill' => $this->graph_title_colour
    );
      $lines = $this->CountLines($this->graph_title);
    $title_space = $this->graph_title_font_size * $lines +
      $this->graph_title_space;
    if($pos != 'top' && $pos != 'bottom' && $pos != 'left' && $pos != 'right')
      $pos = 'top';
    $pad_side = 'pad_' . $pos;

    // ensure outside padding is at least the title space
    if($this->{$pad_side} < $this->graph_title_space)
      $this->{$pad_side} = $this->graph_title_space;

    if($pos == 'left') {
      $text['x'] = $this->pad_left + $this->graph_title_font_size;
      $text['y'] = $this->height / 2;
      $text['transform'] = "rotate(270,$text[x],$text[y])";
    } elseif($pos == 'right') {
      $text['x'] = $this->width - $this->pad_right -
        $this->graph_title_font_size;
      $text['y'] = $this->height / 2;
      $text['transform'] = "rotate(90,$text[x],$text[y])";
    } elseif($pos == 'bottom') {
      $text['x'] = $this->width / 2;
      $text['y'] = $this->height - $this->pad_bottom -
        $this->graph_title_font_size * ($lines-1);
    } else {
      $text['x'] = $this->width / 2;
      $text['y'] = $this->pad_top + $this->graph_title_font_size;
    }
    // increase padding by size of text
    $this->{$pad_side} += $title_space;

    // the Text function will break it into lines
    return $this->Text($this->graph_title, $text);
  }


  /**
   * This should be overridden by subclass!
   */
  abstract protected function Draw();

  /**
   * Displays the background image
   */
  protected function BackgroundImage()
  {
    if(!$this->back_image)
      return '';
    $image = array(
      'width' => $this->back_image_width,
      'height' => $this->back_image_height,
      'x' => $this->back_image_left,
      'y' => $this->back_image_top,
      'xlink:href' => $this->back_image,
      'preserveAspectRatio' => 
        ($this->back_image_mode == 'stretch' ? 'none' : 'xMinYMin')
    );
    $style = array();
    if($this->back_image_opacity)
      $style['opacity'] = $this->back_image_opacity;

    $contents = '';
    if($this->back_image_mode == 'tile') {
      $image['x'] = 0; $image['y'] = 0;
      $im = $this->Element('image', $image, $style);
      $pattern = array(
        'id' => 'bgimage',
        'width' => $this->back_image_width,
        'height' => $this->back_image_height,
        'x' => $this->back_image_left,
        'y' => $this->back_image_top,
        'patternUnits' => 'userSpaceOnUse'
      );
      // tiled image becomes a pattern to replace background colour
      $this->defs[] = $this->Element('pattern', $pattern, NULL, $im);
      $this->back_colour = 'url(#bgimage)';
    } else {
      $im = $this->Element('image', $image, $style);
      $contents .= $im;
    }
    return $contents;
  }

  /**
   * Displays the background
   */
  protected function Canvas()
  {
    $bg = $this->BackgroundImage();
    $canvas = array(
      'width' => '100%', 'height' => '100%',
      'fill' => $this->back_colour,
      'stroke-width' => 0
    );
    if($this->back_round)
      $canvas['rx'] = $canvas['ry'] = $this->back_round;
    if($bg == '' && $this->back_stroke_width) {
      $canvas['stroke-width'] = $this->back_stroke_width;
      $canvas['stroke'] = $this->back_stroke_colour;
    }
    $c_el = $this->Element('rect', $canvas);
    $this->defs[] = $this->Element('clipPath', array('id' => 'canvas'),
      NULL, $c_el);
    if($bg != '') {
      $c_el .= $bg;
      if($this->back_stroke_width) {
        $canvas['stroke-width'] = $this->back_stroke_width;
        $canvas['stroke'] = $this->back_stroke_colour;
        $canvas['fill'] = 'none';
        $c_el .= $this->Element('rect', $canvas);
      }
    }
    return $c_el;
  }

  /**
   * Fits text to a box - text will be bottom-aligned
   */
  protected function TextFit($text, $x, $y, $w, $h, $attribs = NULL,
    $styles = NULL)
  {
    $pos = array('onload' => "textFit(evt,$x,$y,$w,$h)");
    if(is_array($attribs))
      $pos = array_merge($attribs, $pos);
    $txt = $this->Element('text', $pos, $styles, $text);

    /** Uncomment to see the box
    $rect = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
      'fill' => 'none', 'stroke' => 'black');
    $txt .= $this->Element('rect', $rect);
    **/
    $this->AddFunction('textFit');
    return $txt;
  }

  /**
   * Returns a text element, with tspans for subsequent lines
   */
  protected function Text($text, $attribs, $styles = NULL)
  {
    $lines = explode("\n", $text);
    $content = array_shift($lines);

    foreach($lines as $line) {
      $content .= $this->Element('tspan',
        array('x' => $attribs['x'], 'dy' => $attribs['font-size']),
        NULL, $line);
    }
    return $this->Element('text', $attribs, $styles, $content); 
  }

  /**
   * Returns the number of lines in a string
   **/
  protected static function CountLines($text)
  {
    $c = 1;
    $pos = 0;
    while(($pos = strpos($text, "\n", $pos)) !== FALSE) {
      ++$c;
      ++$pos;
    }
    return $c;
  }

  /**
   * Displays readable (hopefully) error message
   */
  protected function ErrorText($error)
  {
    $text = array('x' => $this->pad_left, 'y' => $this->height - 3);
    $style = array(
      'font-family' => 'monospace',
      'font-size' => '12px',
      'font-weight' => 'bold',
    );
    
    $e = $this->ContrastText($text['x'], $text['y'], $error, 'blue',
      'white', $style);
    return $e;
  }

  /**
   * Displays high-contrast text
   */
  protected function ContrastText($x, $y, $text, $fcolour = 'black',
    $bcolour = 'white', $properties = NULL, $styles = NULL)
  {
    $props = array('transform' => 'translate(' . $x . ',' . $y . ')',
      'fill' => $fcolour);
    if(is_array($properties))
      $props = array_merge($properties, $props);

    $bg = $this->Element('text',
      array('stroke-width' => '2px', 'stroke' => $bcolour), NULL, $text);
    $fg = $this->Element('text', NULL, NULL, $text);
    return $this->Element('g', $props, $styles, $bg . $fg);
  }
 
  /**
   * Formats lines of text
   */
  protected function TextLines($text, $x, $line_spacing)
  {
    $start_pos = - (count($text) - 1) / 2 * $line_spacing;
    $dy = $start_pos;

    $string = '';
    foreach($text as $line) {
      $string .= $this->Element('tspan', array('x' => $x, 'dy' => $dy),
        NULL, $line);
      if($dy == $start_pos)
        $dy = $line_spacing;
    }

    return $string;
  }

  /**
   * Draws an element
   */
  protected function Element($name, $attribs = NULL, $styles = NULL,
    $content = NULL)
  {
    // these properties require units to work well
    $require_units = array('stroke-width', 'stroke-dashoffset',
      'font-size', 'baseline-shift', 'kerning', 'letter-spacing',
      'word-spacing');

    if($this->namespace && strpos($name, ':') === FALSE)
      $name = 'svg:' . $name;
    $element = '<' . $name;
    if(is_array($attribs))
      foreach($attribs as $attr => $val) {

        // if units required, add px
        if(is_numeric($val)) {
          if(array_search($attr, $require_units) !== FALSE)
            $val .= 'px';
        } else {
          $val = htmlspecialchars($val);
        }
        $element .= ' ' . $attr . '="' . $val . '"';
      }

    if(is_array($styles)) {
      $element .= ' style="';
      foreach($styles as $attr => $val) {
        // check units again
        if(is_numeric($val)) {
          if(array_search($attr, $require_units) !== FALSE)
            $val .= 'px';
        } else {
          $val = htmlspecialchars($val);
        }
        $element .= $attr . ':' . $val . ';';
      }
      $element .= '"';
    }

    if(is_null($content))
      $element .= "/>\n";
    else
      $element .= '>' . $content . '</' . $name . ">\n";

    return $element;
  }

  /**
   * Returns a link URL or NULL if none
   */
  protected function GetLinkURL($key, $row = 0)
  {
    if(!is_array($this->links[$row]) || !isset($this->links[$row][$key]))
      return NULL;

    $link = $this->links[$row][$key];
    if(strpos($link,'//') === FALSE) // check for absolute links
      $link = $this->link_base . $link;

    return $link;
  }

  /**
   * Retrieves a link
   */
  protected function GetLink($key, $content, $row = 0)
  {
    $link = $this->GetLinkURL($key, $row);
    if(is_null($link))
      return $content;

    $link_attr = array('xlink:href' => $link, 'target' => $this->link_target);
    return $this->Element('a', $link_attr, NULL, $content);
  }

  /**
   * Returns a colour reference
   */
  protected function GetColour($key, $no_gradient = FALSE)
  {
    if(!isset($this->colours[$key]))
      return 'none';
    if(is_array($this->colours[$key]))
      if($no_gradient) // sometimes gradients look awful
        return $this->colours[$key][0];
      else
        return 'url(#gradient' . $key . ')';
    return $this->colours[$key];
  }

  /**
   * Checks that the data are valid
   */
  protected function CheckValues(&$values)
  {
    if(count($values) == 0 || count($values[0]) == 0)
      throw new Exception('No data');
  }

  /**
   * Checks if the keys are associative
   */
  protected function AssociativeKeys()
  {
    $values = $this->GetValues();
    foreach(array_keys($values) as $k)
      if(!is_integer($k))
        return true;
    return false;
  }

  /**
   * Sets the stroke options for an element
   */
  protected function SetStroke(&$attr, $line_join = null)
  {
    if($this->stroke_width > 0) {
      $attr['stroke'] = $this->stroke_colour;
      $attr['stroke-width'] = $this->stroke_width;
      if(!is_null($line_join))
        $attr['stroke-linejoin'] = $line_join;
    }
  }

  /**
   * Creates a new ID for an element
   */
  public function NewID()
  {
    if(!isset($this->last_id))
      $this->last_id = 0;
    return 'e' . base_convert(++$this->last_id, 10, 36);
  }

  /**
   * Loads the Javascript class
   */
  private function LoadJavascript()
  {
    if(!isset($this->javascript)) {
      include_once 'SVGGraphJavascript.php';
      $this->javascript = new SVGGraphJavascript($this->settings, $this);
    }
  }

  /**
   * Adds one or more javascript functions
   */
  protected function AddFunction($name)
  {
    $this->LoadJavascript();
    $fns = func_get_args();
    foreach($fns as $fn)
      $this->javascript->AddFunction($fn);
  }

  /**
   * Adds a Javascript variable
   * - use $value:$more for assoc
   * - use null:$more for array
   */
  public function InsertVariable($var, $value, $more = NULL, $quote = TRUE)
  {
    $this->LoadJavascript();
    $this->javascript->InsertVariable($var, $value, $more, $quote);
  }

  /**
   * Insert a comment into the Javascript section - handy for debugging!
   */
  public function InsertComment($details)
  {
    $this->LoadJavascript();
    $this->javascript->InsertComment($details);
  }

  /**
   * Creates a linear gradient element
   */
  private function MakeLinearGradient($id, $colours)
  {
    $stops = '';
    $direction = 'v';
    if(in_array($colours[count($colours)-1], array('h','v')))
      $direction = array_pop($colours);
    $x2 = $direction == 'v' ? 0 : '100%';
    $y2 = $direction == 'h' ? 0 : '100%';
    $gradient = array('id' => $id, 'x1' => 0, 'x2' => $x2,
      'y1' => 0, 'y2' => $y2);

    $col_mul = 100 / (count($colours) - 1);
    foreach($colours as $pos => $colour) {
      $stop = array('offset' => round($pos * $col_mul) . '%',
        'stop-color' => $colour);
      $stops .= $this->Element('stop', $stop);
    }

    return $this->Element('linearGradient', $gradient, NULL, $stops);
  }

  /**
   * Returns TRUE if the code contains the specified gradient
   */
  private function ContainsGradient(&$code, $gradient)
  {
    return strpos($code, 'gradient' . $gradient) !== FALSE;
  }

  /**
   * Adds an inline event handler to an element's array
   */
  protected function AddEventHandler(&$array, $evt, $code)
  {
    $this->LoadJavascript();
    $this->javascript->AddEventHandler($array, $evt, $code);
  }

  /**
   * Makes an item draggable
   */
  protected function SetDraggable(&$element)
  {
    $this->LoadJavascript();
    $this->javascript->SetDraggable($element);
  }

  /**
   * Makes something auto-hide
   */
  protected function AutoHide(&$element)
  {
    $this->LoadJavascript();
    $this->javascript->AutoHide($element);
  }


  /**
   * Default tooltip contents are key and value, or whatever
   * $key is if $value is not set
   */
  protected function SetTooltip(&$element, $key, $value = NULL,
    $duplicate = FALSE)
  {
    $text = $this->TooltipText($key, $value);
    $this->LoadJavascript();
    $this->javascript->SetTooltip($element, $text, $duplicate);
  }

  /**
   * Sets the text to use for a tooltip
   */
  protected function TooltipText($key, $value = NULL)
  {
    if(is_null($value))
      return addslashes($key);
    return addslashes($key . ', ' . $value);
  }

  /**
   * Sets the fader for an element
   */
  protected function SetFader(&$element, $in, $out, $target = NULL,
    $duplicate = FALSE)
  {
    $this->LoadJavascript();
    $this->javascript->SetFader($element, $in, $out, $target, $duplicate);
  }

  /**
   * Add an overlaid copy of an element, with opacity of 0
   * $from and $to are the IDs of the source and destination
   */
  protected function AddOverlay($from, $to)
  {
    $this->LoadJavascript();
    $this->javascript->AddOverlay($from, $to);
  }

  /**
   * Returns the SVG document
   */
  public function Fetch($header = TRUE)
  {
    $content = '';
    if($header) {
      // '>' is with \n so as not to confuse syntax highlighting
      $content .= '<?xml version="1.0" standalone="no"?' . ">\n";
      if($this->doctype)
        $content .= '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" ' .
        '"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\n";
    }

    // set the precision - PHP default is 14 digits!
    $old_precision = ini_set('precision', $this->precision);

    // display title and description if available
    $heading = '';
    if($this->title)
      $heading .= $this->Element('title', NULL, NULL, $this->title);
    if($this->description)
      $heading .= $this->Element('desc', NULL, NULL, $this->description);

    try {
      $this->CheckValues($this->values);
      // get the body content from the subclass
      $body = $this->DrawGraph();
    } catch(Exception $e) {
      $body = $this->ErrorText($e->getMessage());
    }

    $svg = array(
      'width' => $this->width, 'height' => $this->height, 
      'version' => '1.1', 
      'xmlns:xlink' => 'http://www.w3.org/1999/xlink'
    );
    if(isset($this->javascript)) {
      $variables = $this->javascript->GetVariables();
      $functions = $this->javascript->GetFunctions();
      $onload = $this->javascript->GetOnload();

      if($variables != '' || $functions != '') {
        $script = array('type' => 'text/javascript');
        $heading .= $this->Element('script', $script, NULL,
          "<![CDATA[\n$variables\n$functions\n// ]]>");
      }
      if($onload != '')
        $svg['onload'] = $onload;
    }

    // insert any gradients that are used
    foreach($this->colours as $key => $c)
      if(is_array($c) && $this->ContainsGradient($body, $key))
        $this->defs[] = $this->MakeLinearGradient('gradient' . $key, $c);

    // show defs and body content
    $heading .= $this->Element('defs', NULL, NULL, implode('', $this->defs));
    if($this->namespace)
      $svg['xmlns:svg'] = "http://www.w3.org/2000/svg";
    else
      $svg['xmlns'] = "http://www.w3.org/2000/svg";

    // add any extra namespaces
    foreach($this->namespaces as $ns => $url)
      $svg['xmlns:' . $ns] = $url;

    // display version string
    if($this->show_version) {
      $text = array('x' => $this->pad_left, 'y' => $this->height - 3);
      $style = array(
        'font-family' => 'monospace', 'font-size' => '12px',
        'font-weight' => 'bold',
      );
      $body .= $this->ContrastText($text['x'], $text['y'], SVGGRAPH_VERSION,
        'blue', 'white', $style);
    }

    $content .= $this->Element('svg', $svg, NULL, $heading . $body);
    // replace PHP's precision
    ini_set('precision', $old_precision);

    return $content;
  }

  /**
   * Renders the SVG document
   */
  public function Render($header = TRUE, $content_type = TRUE)
  {
    $mime_header = 'Content-type: image/svg+xml; charset=UTF-8';
    try {
      $content = $this->Fetch($header);
      if($content_type)
        header($mime_header);
      echo $content;
    } catch(Exception $e) {
      if($content_type)
        header($mime_header);
      $this->ErrorText($e);
    }
  }

  private $svg_colours = "aliceblue antiquewhite aqua aquamarine azure beige bisque black blanchedalmond blue blueviolet brown burlywood cadetblue chartreuse chocolate coral cornflowerblue cornsilk crimson cyan darkblue darkcyan darkgoldenrod darkgray darkgreen darkgrey darkkhaki darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon darkseagreen darkslateblue darkslategray darkslategrey darkturquoise darkviolet deeppink deepskyblue dimgray dimgrey dodgerblue firebrick floralwhite forestgreen fuchsia gainsboro ghostwhite gold goldenrod gray grey green greenyellow honeydew hotpink indianred indigo ivory khaki lavender lavenderblush lawngreen lemonchiffon lightblue lightcoral lightcyan lightgoldenrodyellow lightgray lightgreen lightgrey lightpink lightsalmon lightseagreen lightskyblue lightslategray lightslategrey lightsteelblue lightyellow lime limegreen linen magenta maroon mediumaquamarine mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue mediumspringgreen mediumturquoise mediumvioletred midnightblue mintcream mistyrose moccasin navajowhite navy oldlace olive olivedrab orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred papayawhip peachpuff peru pink plum powderblue purple red rosybrown royalblue saddlebrown salmon sandybrown seagreen seashell sienna silver skyblue slateblue slategray slategrey snow springgreen steelblue tan teal thistle tomato turquoise violet wheat white whitesmoke yellow yellowgreen";

}

