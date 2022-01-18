<?php

namespace TheWebmen\Menustructure\Model;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use UncleCheese\DisplayLogic\Forms\Wrapper;

class MenuItem extends DataObject {

    private static $link_types = [
        'page' => 'Page',
        'url' => 'URL',
        'file' => 'File',
        'no-link' => 'Not linked'
    ];

    private static $table_name = 'Menustructure_MenuItem';

    private static $db = [
        'Title' => 'Varchar',
        'LinkType' => 'Varchar',
        'Url' => 'Varchar(255)',
        'OpenInNewWindow' => 'Boolean',
        'Sort' => 'Int',
        'AnchorText' => 'Varchar',
    ];

    private static $has_one = [
        'Image' => Image::class,
        'File' => File::class,
        'Menu' => Menu::class,
        'ParentItem' => MenuItem::class,
        'LinkedPage' => SiteTree::class
    ];

    private static $has_many = [
        'Items' => MenuItem::class
    ];

    private static $owns = [
      'Image',
      'File'
    ];

    private static $summary_fields = [
        'Title',
        'LinkType',
        'OpenInNewWindow',
    ];

    private static $default_sort = 'Sort';

    private static $enable_page_anchor = false;

    /**
     * @return \SilverStripe\Forms\FieldList
     */
    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function($fields) {
            $fields->removeByName('Sort');
            $fields->removeByName('ParentItemID');
            $fields->removeByName('MenuID');

            $fields->replaceField('LinkType', DropdownField::create('LinkType', $this->fieldLabel('LinkType'), $this->getLinkTypes()));
            $fields->replaceField('LinkedPageID', $linkedPageWrapper = Wrapper::create(TreeDropdownField::create('LinkedPageID', $this->fieldLabel('LinkedPage'), SiteTree::class)));

            $linkedPageWrapper->displayIf('LinkType')->isEqualTo('page');
            $fields->dataFieldByName('File')->displayIf('LinkType')->isEqualTo('file');
            $fields->dataFieldByName('Url')->displayIf('LinkType')->isEqualTo('url');
            $fields->dataFieldByName('OpenInNewWindow')->displayIf('LinkType')->isEqualTo('page')->orIf('LinkType')->isEqualTo('url')->orIf('LinkType')->isEqualTo('file');

            if (self::config()->enable_page_anchor) {
                $fields->dataFieldByName('AnchorText')->displayIf('LinkType')->isEqualTo('page');
                $fields->addFieldToTab('Root.Main', $fields->dataFieldByName('AnchorText'));
            } else {
                $fields->removeByName('AnchorText');
            }

            $fields->addFieldToTab('Root.Main', $fields->dataFieldByName('OpenInNewWindow'));
            $fields->addFieldToTab('Root.Main', $fields->dataFieldByName('Image')->setFolderName('Menus')->setDescription('Optional image, can be used in some templates.'));

            $fields->removeByName('Items');
            if($this->exists()){
                $gridConfig = new GridFieldConfig_RecordEditor();
                $gridConfig->addComponent(GridFieldOrderableRows::create());
                $fields->addFieldToTab('Root.Main', GridField::create('Items', 'Items', $this->Items(), $gridConfig));
            }
        });

        return parent::getCMSFields();
    }

    private function getLinkTypes() {
        $linkTypes = self::$link_types;
        $this->extend('updateLinkTypes', $linkTypes);
        return $linkTypes;
    }

    /**
     * @return bool|mixed
     */
    public function getLink(){
        switch ($this->LinkType) {
            case 'url':
                return $this->Url;
                break;
            case 'page':
                $link = $this->LinkedPage()->Link();

                if (self::config()->enable_page_anchor && $this->AnchorText) {
                    $link .= sprintf('#%s', $this->AnchorText);
                }

                return $link;
                break;
            case 'file':
                return $this->File()->Link();
                break;
        }
        return false;
    }

    /**
     * @return string
     */
    public function LinkingMode(){
        if($this->LinkType == 'page'){
            return Controller::curr()->ID == $this->LinkedPageID ? 'current' : 'link';
        }
        return 'link';
    }

    /**
     * @param null $member
     * @param array $context
     * @return bool
     */
    public function canCreate($member = null, $context = array())
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_TheWebmen\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canCreate($member, $context);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canView($member = null)
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_TheWebmen\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canView($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canEdit($member = null)
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_TheWebmen\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canEdit($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canDelete($member = null)
    {
        if (Permission::checkMember($member, 'CMS_ACCESS_TheWebmen\Menustructure\Admin\MenusAdmin')) {
            return true;
        }

        return parent::canDelete($member);
    }

    /**
     * Recursive delete
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        foreach($this->Items() as $item){
            $item->delete();
        }
    }

}
