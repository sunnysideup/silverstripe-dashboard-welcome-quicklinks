<?php

namespace Sunnysideup\DashboardWelcomeQuicklinks\Api;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Upload;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\MFA\Report\EnabledMembers;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\VersionedAdmin\ArchiveAdmin;
use Sunnysideup\DashboardWelcomeQuicklinks\Interfaces\DashboardWelcomeQuickLinksProvider;

class DefaultDashboardProvider implements DashboardWelcomeQuickLinksProvider
{
    use Configurable;
    protected $links = [];

    public function provideDashboardWelcomeQuickLinks(): array
    {
        $this->addPagesLinks();
        $this->addFindPages();
        $this->addFilesAndImages();
        $this->addSiteConfigLinks();
        $this->addSecurityLinks();
        $this->addModelAdminLinks();
        $this->addMeLinks();
        return $this->links;
    }

    private static $new_phrase = '+';
    private static $review_phrase = '☑';
    private static $edit_phrase = '✎';


    protected function addPagesLinks()
    {
        $this->addGroup('PAGES', 'Pages', 10);
        $this->addLink('PAGES', $this->phrase('add'). ' Page', '/admin/pages/add');
        $pagesCount = DataObject::get('Page')->count();
        $this->addLink('PAGES', $this->phrase('edit'). ' Pages ('.$pagesCount.')', '/admin/pages');
        $pageLastEdited = DataObject::get_one('Page', '', true, 'LastEdited DESC');
        if ($pageLastEdited) {
            $this->addLink('PAGES', '✎ Last Edited Page: '.$pageLastEdited->Title, $pageLastEdited->CMSEditLink());
        }
        $this->addLink('PAGES', $this->phrase('review'). ' Page Reports', '/admin/reports');
        $lastWeekLink = '/admin/pages?'.'q[LastEditedFrom]='.date('Y-m-d', strtotime('-1 week'));
        $this->addLink('PAGES', $this->phrase('review'). ' Recently Modified Pages', $lastWeekLink);
    }

    protected function addFindPages()
    {
        $pages = [];
        foreach (ClassInfo::subclassesFor(SiteTree::class, false) as $className) {
            $pages[$className] = $className;

        }
        $this->addGroup('PAGEFILTER', 'Page Types ('.count($pages).')', 300);
        $count = 0;
        foreach($pages as $pageClassName) {
            $pageCount = $pageClassName::get()->count();
            if($pageCount < 1) {
                continue;
            }
            $count++;
            if($count > 12) {
                break;
            }
            $page = Injector::inst()->get($pageClassName);
            $pageTitle = $page->i18n_singular_name();
            $query = urlencode('q[ClassName]='.$pageClassName);
            $link = 'admin/pages?' . $query;
            $this->addLink('PAGEFILTER', $this->phrase('edit'). ' '.$pageTitle.' ('.$pageCount.')', $link);
        }
    }
    protected function addFilesAndImages()
    {
        // 'Files ('.$filesCount.') and Images ('.$imageCount.')'
        $this->addGroup('FILESANDIMAGES', 'Files and Images', 20);
        $uploadFolderName = Config::inst()->get(Upload::class, 'uploads_folder');
        $uploadFolder = Folder::find_or_make($uploadFolderName);
        // all
        $this->addLink('FILESANDIMAGES', $this->phrase('edit'). ' Open File Browswer', '/admin/assets');
        // per type
        $filesCount = File::get()->exclude(['ClassName' => [Folder::class, Image::class]])->count();
        $imageCount = File::get()->filter(['ClassName' => [Image::class]])->count();
        $this->addLink('FILESANDIMAGES', $this->phrase('review'). ' Images ('.$imageCount.')', 'admin/assets?filter[appCategory]=IMAGE');
        $this->addLink('FILESANDIMAGES', $this->phrase('review'). ' Files ('.$filesCount.')', 'admin/assets?filter[appCategory]=DOCUMENT');

        // default upload folder
        $this->addLink('FILESANDIMAGES', $this->phrase('review'). ' Open Default Upload Folder', $uploadFolder->CMSEditLink());

        // recent
        $lastWeekLink = '/admin/assets?'.'filter[lastEditedFrom]='.date('Y-m-d', strtotime('-1 week')).'&view=tile';
        $this->addLink('FILESANDIMAGES', $this->phrase('review'). ' Recently modified Files', $lastWeekLink);
    }

    protected function addSiteConfigLinks()
    {
        $this->addGroup('SITECONFIG', 'Site Wide Configuration', 20);
        $this->addLink('SITECONFIG', $this->phrase('review'). ' Site Settings', '/admin/settings');
    }

    protected function addSecurityLinks()
    {
        $this->addGroup('SECURITY', 'Security', 30);
        $this->addLink('SECURITY', $this->phrase('add'). ' User', '/admin/security/users/EditForm/field/users/item/new');
        $userCount = Member::get()->count();
        $groupCount = Group::get()->count();
        $this->addLink('SECURITY', $this->phrase('review'). ' Users ('.$userCount.')', '/admin/security');
        $this->addLink('SECURITY', $this->phrase('review'). ' Groups  ('.$groupCount.')', '/admin/security/groups');
        DefaultAdminService::singleton()->extend('addSecurityLinks', $this);
        $adminGroup = Permission::get_groups_by_permission('ADMIN')->first();
        if($adminGroup) {
            $userCount = $adminGroup->Members()->count();
            $this->addLink('SECURITY', $this->phrase('review'). ' Administrators ('.$userCount.')', '/admin/security/groups/EditForm/field/groups/item/'.$adminGroup->ID.'/edit');
        }
        if(class_exists(EnabledMembers::class)) {
            $obj = Injector::inst()->get(EnabledMembers::class);
            $this->addLink('SECURITY', $this->phrase('review'). ' Multi-Factor Authentication Status', $obj->getLink());
        }
    }




    protected function addModelAdminLinks()
    {
        $modelAdmins = [];
        foreach (ClassInfo::subclassesFor(ModelAdmin::class, false) as $className) {
            if($className === ArchiveAdmin::class) {
                continue;
            }
            $modelAdmins[$className] = $className;

        }
        foreach($modelAdmins as $modelAdminClassName) {
            $groupAdded = false;
            $ma = Injector::inst()->get($modelAdminClassName);
            $mas = $ma->getManagedModels();
            if(count($mas)) {
                $numberOfModels = count($mas);
                $groupCode = strtoupper($modelAdminClassName);
                $count = 0;
                foreach($mas as $model => $title) {
                    $count++;
                    if($count > 7) {
                        break;
                    }
                    if(is_array($title)) {
                        $title = $title['title'];
                        $model = $title['dataClass'] ?? $model;
                    }
                    if(! class_exists($model)) {
                        continue;
                    }
                    if(! $groupAdded) {
                        $this->addGroup($groupCode, $ma->menu_title(), 100);
                        $groupAdded = true;
                    }
                    $obj = DataObject::singleton($model);
                    $link = '';
                    if($obj->hasMethod('CMSListLink')) {
                        $link = $obj->CMSListLink();
                    } else {
                        $link = $ma->getLinkForModelTab($model);
                    }
                    $objectCount = $model::get()->count();
                    $titleContainsObjectCount = strpos($title, ' ('.$objectCount.')');
                    if($titleContainsObjectCount === false) {
                        $title .= ' ('.$objectCount.')';
                    }
                    $this->addLink($groupCode, $this->phrase('edit'). ' '.$title, $link);
                    if($numberOfModels < 4) {
                        $obj = Injector::inst()->get($model);
                        if($obj->canCreate()) {
                            $classNameEscaped = str_replace('\\', '-', $model);
                            $linkNew = $link .= '/EditForm/field/'.$classNameEscaped.'/item/new';
                            $this->addLink($groupCode, $this->phrase('add'). ' '.$obj->i18n_singular_name(), $linkNew);
                        }
                    }
                }
            }
        }
    }



    protected function addMeLinks()
    {
        $this->addGroup('ME', 'My Account', 200);
        $this->addLink('ME', $this->phrase('edit') . '  My Details (there is just one of you!)', '/admin/myprofile');
        $this->addLink('ME', $this->phrase('review') . '  Test Password Reset', 'Security/lostpassword');
        $this->addLink('ME', $this->phrase('review') . '  Log-out', '/Security/logout');
    }


    protected function addGroup(string $groupCode, string $title, $sort)
    {
        $this->links[$groupCode] = [
            'Title' => $title,
            'SortOrdre' => $sort,
        ];
    }

    protected function addLink($groupCode, $title, $link)
    {
        $this->links[$groupCode]['Items'][] = [
            'Title' => $title,
            'Link' => $link,
        ];
    }

    protected function phrase(string $phrase): string
    {
        if($phrase === 'add') {
            $phrase = $this->config()->get('new_phrase');
        } elseif($phrase === 'review') {
            $phrase = $this->config()->get('review_phrase');
        } elseif($phrase === 'edit') {
            $phrase = $this->config()->get('edit_phrase');
        }
        return _t('DashboardWelcomeQuicklinks.'.$phrase, $phrase);
    }

}
