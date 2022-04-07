<?php

class hubSettingsCategorySaveController extends waJsonController
{
    protected $category_model = null;

    public function execute()
    {
        $this->category_model = new hubCategoryModel();

        $id = (int)waRequest::request('id');
        if (!$id) {
            $hub_id = (int)waRequest::request('hub_id');
            $hub_model = new hubHubModel();
            if ($hub_model->getById($hub_id)) {
                $id = $this->save($this->getData(), 0, $hub_id);
            } else {
                throw new waException(_w('Hub is not found', 404));
            }

        } else if (waRequest::request('delete_logo')) {
            $this->deleteLogo($id);
        } else {
            $id = $this->save($this->getData(), $id);
        }

        if ($id) {
            $this->response = $this->category_model->getById($id);
            $this->response['glyph_html'] = $this->getGlyphHtml($this->response);
        }
    }

    private function getGlyphHtml($c)
    {
        if ($c['type']) {
            $result = hubHelper::getIcon('funnel');
            if (strpos($c['conditions'], 'tag_id=') === 0) {
                $result = hubHelper::getIcon('tags');
            } else {
                if ($type_id = intval(str_replace('type_id=', '', $c['conditions']))) {
                    $types = hubHelper::getTypes();
                    if (!empty($types[$type_id])) {
                        $result = hubHelper::getGlyph($types[$type_id]['glyph']);
                    }
                }
            }
        } else {
            $result = hubHelper::getIcon('folder');
        }
        return $result;
    }

    private function save($data, $id, $hub_id = null)
    {
        $new = false;
        if (!$id) {
            $data['hub_id'] = $hub_id;
            if ($this->getUser()->getRights($this->getApp(), 'hub.'.$data['hub_id']) < hubRightConfig::RIGHT_FULL) {
                throw new waRightsException('Access denied');
            }
            $id = $this->category_model->add($data);
            $new = true;
        } elseif ($category = $this->category_model->getById($id)) {
            if ($this->getUser()->getRights($this->getApp(), 'hub.'.$category['hub_id']) < hubRightConfig::RIGHT_FULL) {
                throw new waRightsException('Access denied');
            }
            $this->category_model->update($id, $data, $this->errors);
        } else {
            throw new waException('Category not found', 404);
        }

        if ($id && !empty($data['og'])) {
            $hub_category_og_model = new hubCategoryOgModel();
            $hub_category_og_model->set($id, $data['og']);
        }

        try {
            $this->saveLogo($id);
        } catch (Exception $ex) {
            if (!$new) {
                $this->errors['category_logo'] = $ex->getMessage();
            }
        }

        return $id;
    }

    /**
     * @param $id
     * @throws Exception
     * @throws waException
     */
    private function saveLogo($id)
    {
        if (($file = waRequest::file('category_logo')) && ($file->uploaded())) {
            if (!preg_match('@^(jpe?g|png)$@ui', $e = $file->extension)) {
                throw new waException(_w('Only PNG and JPEG images are allowed.'));
            }
            $logo_path = wa()->getDataPath(sprintf('categories/%d/', $id), true, $this->getAppId());
            if ($image = $file->waImage()) {
                if (!preg_match('@^(jpe?g|png)$@ui', $e = $image->getExt())) {
                    throw new waException('Only PNG and JPEG images are allowed.'.$e);
                }
                waFiles::delete($logo_path);
                waFiles::create($logo_path);
                $logo = preg_replace('@[^a-z0-9_\-]+@', '', waLocale::transliterate($file->name));
                if (empty($logo)) {
                    $logo = $id;
                }
                $logo .= '.'.$image->getExt();
                $file->moveTo($logo_path, $logo);
                $this->category_model->updateById($id, compact('logo'));
            }
        }
    }

    private function deleteLogo($id)
    {
        $category = $this->category_model->getById($id);
        if (!$category || !$category['logo']) {
            return;
        }

        $logo_path = wa()->getDataPath(sprintf('categories/%d/%s', $id, $category['logo']), true, $this->getAppId());
        is_writable($logo_path) && waFiles::delete($logo_path);
        $this->category_model->updateById($id, array(
            'logo' => '',
        ));
    }

    private function getData()
    {
        $data = waRequest::post('category', array(), waRequest::TYPE_ARRAY);
        $data['url'] = trim(ifset($data['url'], ''), "/ \t\r\n");
        $data['type'] = intval(!!ifset($data['type'], 0));
        $data['og'] = waRequest::post('og', [], waRequest::TYPE_ARRAY);
        $data += array(
            'enable_sorting' => 0,
        );

        // Prepare conditions for dynamic category
        if (hubCategoryModel::TYPE_DYNAMIC == $data['type']) {

            $data['conditions'] = ifempty($data['conditions'], array());

            // Convert tag names into tag_ids, creating new tags if needed
            $hub_id = waRequest::request('hub_id', 0, 'int');
            $tag_names = waRequest::request('tags', '', 'string');
            if ($hub_id && $tag_names) {
                $tag_model = new hubTagModel();
                $tag_names = array_fill_keys(explode(',', $tag_names), true);

                // Get existing tag_ids by name
                $tag_ids = array();
                foreach ($tag_model->getByField(array('name' => array_keys($tag_names), 'hub_id' => $hub_id), true) as $tag) {
                    unset($tag_names[$tag['name']]);
                    $tag_ids[] = $tag['id'];
                }

                // Create new tags
                foreach (array_keys($tag_names) as $tag_name) {
                    try {
                        $tag_ids[] = $tag_model->insert(
                            array(
                                'name'   => $tag_name,
                                'hub_id' => $hub_id,
                            )
                        );
                    } catch (waDbException $e) {
                    }
                }

                if ($tag_ids) {
                    $data['conditions']['tag_id'] = $tag_ids;
                }
            }

            // Prepare conditions for collection
            foreach ($data['conditions'] as $field => &$values) {
                $values = sprintf('%s=%s', $field, implode('||', (array)$values));
                unset($values);
            }
            $data['conditions'] = implode('&', $data['conditions']);
        } else {
            $data['conditions'] = null;
        }

        return $data;
    }
}
