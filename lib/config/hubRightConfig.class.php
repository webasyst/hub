<?php

/**
 * Class containing app's access rights settings description
 * http://www.webasyst.com/framework/docs/dev/access-rights/
 */
class hubRightConfig extends waRightConfig
{
    const RIGHT_NONE = 0;
    const RIGHT_READ = 1;
    const RIGHT_READ_WRITE = 2;
    const RIGHT_FULL = 3;

    /** @var array hub_id => hub data */
    protected $hubs = null;

    public function init()
    {
        $hub_model = new hubHubModel();
        $this->hubs = $hub_model->getAll('id');

        // Access to Pages and Design section
        $this->addItem('pages', _ws('Can edit pages'), 'checkbox');
        $this->addItem('design', _ws('Can edit design'), 'checkbox');

        // For each hub there's a line with access rights set up
        $items = array();
        foreach ($this->hubs as $hub) {
            $items[$hub['id']] = $hub['name'];
        }
        $this->addItem(
            'hub',
            _w('Hubs'),
            'selectlist',
            array(
                'items'    => $items,
                'position' => 'right',
                'options'  => array(
                    self::RIGHT_NONE       => _w('No access'),
                    self::RIGHT_READ       => _w('Read and comment'),
                    self::RIGHT_READ_WRITE => _w('Read, comment, and publish new articles'),
                    self::RIGHT_FULL       => _w('Full access'),
                ),
            )
        );
    }

    protected function getItemHTML($name, $label, $type, $params, $rights, $inherited = null)
    {
        //
        // "No access" and "read only" options do not make sense for public hubs.
        // We override one HTML control to disable those options.
        //
        if ($type == 'select' && substr($name, 0, 4) == 'hub.') {
            if (!isset($params['options']) || !$params['options']) {
                return '';
            }

            $o = $params['options'];
            $min_right = hubRightConfig::RIGHT_NONE;
            $hub_id = substr($name, 4);
            if (!empty($this->hubs[$hub_id]['status'])) {
                unset(
                    $o[hubRightConfig::RIGHT_NONE],
                    $o[hubRightConfig::RIGHT_READ]
                );
                $min_right = hubRightConfig::RIGHT_READ_WRITE;
            }

            $own = ifset($rights[$name]);
            $own > $min_right || $own = $min_right;
            $group = $inherited ? ifset($inherited[$name]) : 0;
            $group > $min_right || $group = $min_right;

            $oHTML = array();
            foreach ($o as $val => $opt) {
                $oHTML[] = '<option value="'.$val.'"'.($own == $val ? ' selected="selected"' : '').'>'.htmlspecialchars($opt).'</option>';
            }
            $oHTML = implode('', $oHTML);

            $cssclass = empty($params['cssclass']) ? '' : ' class="'.$params['cssclass'].'"';
            return '<tr'.$cssclass.'>'.
            '<td><div>'.
            $label.
            (empty($this->hubs[$hub_id]['status']) ? ' <i class="icon10 lock-bw"></i>' : '').
            '</div></td>'.
            ($inherited !== null ? '<td><strong>'.$o[max($own, $group)].'</strong></td>' : '').
            '<td><input type="hidden" name="app['.$name.']" value="0">'.
            '<select name="app['.$name.']">'.$oHTML.'</select>'.
            '</td>'.
            ($inherited !== null ? '<td>'.($inherited && isset($inherited['backend']) ? $o[$group] : '').'<input type="hidden" class="g-value" value="'.$group.'"></td>' : '').
            '</tr>';

        }

        return parent::getItemHTML($name, $label, $type, $params, $rights, $inherited);
    }


}
