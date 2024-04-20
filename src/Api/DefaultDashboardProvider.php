<?php

namespace Sunnysideup\DashboardWelcomeQuicklinks\Api;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Upload;
use SilverStripe\CMS\Controllers\CMSSiteTreeFilter_ChangedPages;
use SilverStripe\CMS\Controllers\CMSSiteTreeFilter_StatusDraftPages;
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
        if(Permission::check('ADMIN')) {
            $this->addSecurityLinks();
        }
        $this->addModelAdminLinks();
        $this->addMeLinks();
        return $this->links;
    }


    private static $add_phrase = '+';
    private static $review_phrase = '☑';
    private static $edit_phrase = '✎';
    private static $more_phrase = '... More';
    private static $model_admins_to_skip = [
        ArchiveAdmin::class,
    ];
    private static $pages_to_skip = [

    ];


    protected function addPagesLinks()
    {
        $this->addGroup('PAGES', 'Pages', 10);
        $this->addLink('PAGES', $this->phrase('add'). ' Page', '/admin/pages/add');
        $pagesCount = DataObject::get('Page')->count();
        $draftCount = CMSSiteTreeFilter_StatusDraftPages::create()->getFilteredPages()->count();
        $revisedCount = CMSSiteTreeFilter_ChangedPages::create()->getFilteredPages()->count();
        $this->addLink('PAGES', $this->phrase('edit'). ' Pages ('.$pagesCount.')', '/admin/pages');
        $this->addLink('PAGES', $this->phrase('edit'). ' Unpublished Drafts ('.$draftCount.')', '/admin/pages?q[FilterClass]=SilverStripe\CMS\Controllers\CMSSiteTreeFilter_StatusDraftPages');
        $this->addLink('PAGES', $this->phrase('edit'). ' Unpublished Changes ('.$revisedCount.')', '/admin/pages?q[FilterClass]=SilverStripe\CMS\Controllers\CMSSiteTreeFilter_ChangedPages');
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
        $pagesToSkip = (array) $this->Config()->get('pages_to_skip');
        foreach (ClassInfo::subclassesFor(SiteTree::class, false) as $className) {
            if(in_array($className, $pagesToSkip)) {
                continue;
            }
            $pages[$className] = $className;

        }
        $this->addGroup('PAGEFILTER', 'Page Types ('.count($pages).')', 300);
        $count = 0;
        foreach($pages as $pageClassName) {
            $pageCount = $pageClassName::get()->filter(['ClassName' => $pageClassName])->count();
            if($pageCount < 1) {
                continue;
            }
            $count++;
            if($count > 7) {
                $this->addLink('PAGEFILTER', $this->phrase('more'), '/admin/pages');
                break;
            }
            if($pageCount === 1) {
                $obj = DataObject::get_one($pageClassName, ['ClassName' => $pageClassName]);
                $this->addLink('PAGEFILTER', $this->phrase('edit'). ' '.$pageClassName::singleton()->i18n_singular_name(), $obj->CMSEditLink());
                continue;
            }
            $page = Injector::inst()->get($pageClassName);
            $pageTitle = $page->i18n_singular_name();
            $query = 'q[ClassName]='.$pageClassName;
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
        $skips = (array) $this->Config()->get('model_admins_to_skip');
        foreach (ClassInfo::subclassesFor(ModelAdmin::class, false) as $className) {
            if(in_array($className, $skips)) {
                continue;
            }
            $modelAdmins[$className] = $className;

        }
        foreach($modelAdmins as $modelAdminClassName) {
            $groupAdded = false;
            $ma = Injector::inst()->get($modelAdminClassName);
            if($ma->canView()) {
                $mas = $ma->getManagedModels();
                if(count($mas)) {
                    $numberOfModels = count($mas);
                    $groupCode = strtoupper($modelAdminClassName);
                    $count = 0;
                    foreach($mas as $model => $title) {
                        $count++;
                        if(is_array($title)) {
                            $title = $title['title'];
                            $model = $title['dataClass'] ?? $model;
                        }
                        if(! class_exists($model)) {
                            continue;
                        }
                        if($count > 7) {
                            $this->addLink($groupCode, $this->phrase('more'), $ma->Link());
                            break;
                        }
                        $obj = Injector::inst()->get($model);
                        if($obj && $obj->canView()) {
                            if(! $groupAdded) {
                                $this->addGroup($groupCode, $ma->menu_title(), 100);
                                $groupAdded = true;
                            }
                            // $classNameList = ClassInfo::subclassesFor($model);
                            $objectCount = $model::get()->count();
                            if($objectCount === 1) {
                                $obj = DataObject::get_one($model, ['ClassName' => $model]);
                                if($obj->hasMethod('CMSEditLink')) {
                                    $this->addLink('PAGEFILTER', $this->phrase('edit'). ' '.$model::singleton()->i18n_singular_name(), $obj->CMSEditLink());
                                    continue;
                                }
                            }

                            $link = '';
                            if($obj->hasMethod('CMSListLink')) {
                                $link = $obj->CMSListLink();
                            } if(! $link) {
                                $link = $ma->getLinkForModelTab($model);
                            }
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
        }
    }



    protected function addMeLinks()
    {
        $this->addGroup('ME', 'My Account', 200);
        $this->addLink('ME', $this->phrase('edit') . '  My Details', '/admin/myprofile');
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

    protected function addLink($groupCode, $title, $link, ?bool $hide = false)
    {
        $this->links[$groupCode]['Items'][] = [
            'Title' => $title,
            'Link' => $link,
            'Style' => $hide ? 'display: none;' : '',
        ];
    }

    protected function phrase(string $phrase): string
    {
        $phrase = $this->config()->get($phrase .'_phrase');
        return _t('DashboardWelcomeQuicklinks.'.$phrase, $phrase);
    }

}
