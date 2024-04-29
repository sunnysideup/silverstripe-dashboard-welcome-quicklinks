<?php

namespace Sunnysideup\DashboardWelcomeQuicklinks\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ArrayData;
use Sunnysideup\DashboardWelcomeQuicklinks\Api\DefaultDashboardProvider;
use Sunnysideup\DashboardWelcomeQuicklinks\Interfaces\DashboardWelcomeQuickLinksProvider;

/**
 * Class \Sunnysideup\DashboardWelcomeQuicklinks\Admin\DashboardWelcomeQuicklinks
 *
 */
class DashboardWelcomeQuicklinks extends LeftAndMain
{
    private static $url_segment = 'go';

    private static $use_default_dashboard_provider = true;

    private static $menu_title = 'Quick-links';

    private static $menu_icon_class = 'font-icon-dashboard';

    private static $menu_priority = 99999;

    private static $colour_options = [];
    private static $max_shortcuts_per_group = 7;

    private static $more_phrase = '... More';


    private static $default_colour_options = [
        '#0D47A1',
        '#01579B',
        '#006064',
        '#004D40',
        '#1B5E20',
        '#33691E',
        '#827717',
        '#F57F17',
        '#FF6F00',
        '#E65100',
        '#BF360C',
        '#3E2723',
        '#212121',
        '#B71C1C',
        '#880E4F',
        '#4A148C',
        '#311B92',
        '#1A237E',
    ];

    /**
     * easy to distinguish colours
     *
     * @var array
     */
    private static $default_colour_options1 = [
        '#F2F3F4',
        '#222222',
        '#F3C300',
        '#875692',
        '#F38400',
        '#A1CAF1',
        '#BE0032',
        '#C2B280',
        '#848482',
        '#008856',
        '#E68FAC',
        '#0067A5',
        '#F99379',
        '#604E97',
        '#F6A600',
        '#B3446C',
        '#DCD300',
        '#882D17',
        '#8DB600',
        '#654522',
        '#E25822',
        '#2B3D26',
    ];


    /**
     * light colours
     *
     * @var array
     */
    private static $default_colour_options3 = [
        '#FFEBEE',
        '#FCE4EC',
        '#F3E5F5',
        '#EDE7F6',
        '#E8EAF6',
        '#E3F2FD',
        '#E1F5FE',
        '#E0F7FA',
        '#E0F2F1',
        '#E8F5E9',
        '#F1F8E9',
        '#F9FBE7',
        '#FFFDE7',
        '#FFF8E1',
        '#FFF3E0',
        '#FBE9E7',
        '#EFEBE9',
        '#FAFAFA'
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        // if ($form instanceof HTTPResponse) {
        //     return $form;
        // }
        // $form->Fields()->removeByName('LastVisited');

        $this->updateFormWithQuicklinks($form);

        return $form;
    }

    public function updateFormWithQuicklinks($form)
    {
        $shortcuts = $this->getLinksFromImplementor();
        // print_r($shortcuts);
        $html = '';
        $max = $this->Config()->get('max_shortcuts_per_group');
        if (count($shortcuts)) {
            $html = '<div class="grid-wrapper">';

            usort(
                $shortcuts,
                function ($a, $b) {
                    ($a['SortOrder'] ?? 0) <=> ($b['SortOrder'] ?? 0);
                }
            );

            foreach ($shortcuts as $groupCode => $groupDetails) {
                $colour = '';
                if (!empty($groupDetails['Colour'])) {
                    $colour = 'style="background-color: ' . $groupDetails['Colour'] . '"';
                }
                $icon = '';
                if (!empty($groupDetails['IconClass'])) {
                    $icon = '<i class="' . $groupDetails['IconClass'] . '"></i> ';
                }
                $html .= '
                <div class="grid-cell" ' . $colour . '>
                    <div class="header">
                    <h1>' . $icon . '' . ($groupDetails['Title'] ?? $groupCode) . '</h1>
                    </div>
                    <div class="entries">';
                $items = $groupDetails['Items'] ?? [];
                if (!empty($entry['Link']) && class_exists($entry['Link'])) {
                    $obj = Injector::inst()->get($entry['Link']);
                    if ($obj instanceof DataObject) {
                        $entry['Link'] = DataObject::get_one($entry['Link'])->CMSEditLink();
                    } else {
                        $entry['Link'] = $obj->Link();
                    }
                }
                foreach ($items as $count => $entry) {
                    $entry['Class'] = $entry['Class'] ?? '';
                    $entry['Class'] .= ($count > $max) ? ' more-item' : '';
                    $html .= $this->makeShortCut(
                        (string) $entry['Title'],
                        (string) $entry['Link'],
                        $entry['OnClick'] ?? '',
                        $entry['Script'] ?? '',
                        $entry['Class'] ?? '',
                        $entry['IconClass'] ?? '',
                        $entry['Target'] ?? '',
                    )->Field();
                    if($count > $max && count($items) == $count + 1) {
                        $html .= $this->makeShortCut(
                            $this->Config()->get('more_phrase'),
                            '#', // link
                            'dashboardWelcomeQuickLinksSetupInputAndFilterToggleMore(event); return false;', // onclick
                            '', // script
                            'more-item-more', //class
                        )->Field();
                    }
                }
                $html .= '</div></div>';
            }
        }
        $kc = (array) $this->Config()->get('colour_options');
        if(empty($kc)) {
            $kc = $this->Config()->get('default_colour_options');
        }
        $kcCount = count($kc);
        $colours = '';
        foreach ($kc as $key => $colour) {
            $colours .= ' .grid-wrapper .grid-cell:nth-child(' . $kcCount . 'n+' . ($key + 1) . ') div.header {background-color: ' . $colour . '; color: '.$this->getFontColor($colour).'!important;}';
        }
        $html .= '</div>';
        $html .= '<script>window.setTimeout(dashboardWelcomeQuickLinksSetupInputAndFilter, 500)</script>';
        $html .= '<style>' . $colours . '</style>';
        $form->Fields()->push(LiteralField::create('ShortCuts', $html));
    }

    protected function getLinksFromImplementor()
    {
        $array = [];
        $useDefaultDashboard = (bool) $this->config()->get('use_default_dashboard_provider');
        $classNames = ClassInfo::implementorsOf(DashboardWelcomeQuickLinksProvider::class);
        foreach ($classNames as $className) {
            if($useDefaultDashboard === false && (string) $className === DefaultDashboardProvider::class) {
                continue;
            }
            $array += Injector::inst()->get($className)->provideDashboardWelcomeQuickLinks();
        }
        return $array;
    }

    protected function makeShortCut(string $title, string $link, ?string $onclick = '', ?string $script = '', ?string $class = '', ?string $iconClass = '', ?string $target = '')
    {
        $name = preg_replace('#[\W_]+#u', '', (string) $title);
        $html = '';
        if ($onclick) {
            $onclick = ' onclick="' . $onclick . '"';
        }
        if ($script) {
            $script = '<script>' . $script . '</script>';
        }
        $icon = '';
        if (!empty($iconClass)) {
            $icon = '<i class="' . $iconClass . '"></i> ';
        }
        if(!$target) {
            $target = '_self';
        }
        $target = ' target="'.$target.'"';
        if ($link) {
            $html = '
            ' . $script . '
            <h2 class="' . $class . '">
                ' . $icon . '<a href="' . $link . '" ' . $target . ' ' . $onclick . '>' . $title . '</a>
            </h2>';
        } else {
            $html = '
            ' . $script . '
            <h2>
                ' . $title . '
            </h2>
            ';
        }

        return LiteralField::create(
            $name,
            $html
        );
    }
    /**
     * @return string
     */
    public function Title()
    {
        $app = $this->getApplicationName();
        $siteConfigTitle = SiteConfig::current_site_config()->Title;
        if($siteConfigTitle) {
            $app = $siteConfigTitle . ' ('.$app.')';
        }
        return ($section = $this->SectionTitle()) ? sprintf('%s for %s', $section, $app) : $app;
    }
    /**
     * @param bool $unlinked
     * @return ArrayList<ArrayData>
     */
    public function Breadcrumbs($unlinked = false)
    {
        $items = new ArrayList([
            new ArrayData([
                'Title' => $this->Title(),
                'Link' => ($unlinked) ? false : $this->Link()
            ])
        ]);

        return $items;
    }
    protected function getFontColor(string $backgroundColor): string
    {
        // Convert hex color to RGB
        $r = hexdec(substr($backgroundColor, 1, 2));
        $g = hexdec(substr($backgroundColor, 3, 2));
        $b = hexdec(substr($backgroundColor, 5, 2));

        // Calculate luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // If luminance is greater than 0.5, use black font; otherwise, use white
        return $luminance > 0.5 ? '#222' : '#fff';
    }

}
