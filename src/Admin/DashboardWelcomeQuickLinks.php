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

    private static $use_default_dashboard = true;

    private static $menu_title = 'Quick-links';

    private static $menu_icon_class = 'font-icon-dashboard';

    private static $menu_priority = 99999;

    private static $colour_options = [
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
        $html = '';
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
                foreach ($items as $entry) {
                    $html .= $this->makeShortCut(
                        $entry['Title'],
                        $entry['Link'],
                        $entry['OnClick'] ?? '',
                        $entry['Script'] ?? '',
                        $entry['Style'] ?? '',
                        $entry['IconClass'] ?? '',
                        $entry['Target'] ?? '',
                    )->Field();
                }
                $html .= '</div></div>';
            }
        }
        $kc = (array) $this->Config()->get('colour_options');
        $kcCount = count($kc);
        $colours = '';
        foreach ($kc as $key => $colour) {
            $colours .= ' .grid-wrapper .grid-cell:nth-child(' . $kcCount . 'n+' . ($key + 1) . ') div.header {background-color: ' . $colour . '; color: '.$this->getFontColor($colour).'!important;}';
        }
        $html .= '</div>';
        $html .= '<style>

        .grid-wrapper {
          display: grid;
          grid-template-columns: repeat( auto-fit, minmax(300px, 1fr) );;
          grid-gap: 20px;
        }

        .grid-wrapper .grid-cell {
          max-width: 500px;
          font-size: 150%;
          border-radius: 1rem;
          border: 1px solid #004e7f55;
          display: flex;
          flex-direction: column;
          overflow: hidden;
          box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
          transition: all 0.3s ease;
          opacity: 0.8;
          &:hover {
              transform: scale(1.05);
              box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
              opacity: 1;
          }
        }
        .grid-wrapper .grid-cell > div {
            padding: 20px;
        }
        .grid-wrapper .grid-cell > div.header {
            padding-bottom: 0;
            border-bottom: 1px solid #004e7f55;
        }
        .grid-wrapper .grid-cell > div.header h1 {
            font-weight: 700;
            font-size: 1.3rem!important;
        }
        .grid-wrapper .grid-cell > div.entries {
            background-color: #fff;
            height: 100%;
        }
        ' . $colours . '
        .grid-wrapper .grid-cell div.entries *,
        .grid-wrapper .grid-cell div.entries a:link,
        .grid-wrapper .grid-cell div.entries a:visited {
            color: #222;
        }
        .grid-wrapper .grid-cell div.entries a:link:hover,
        .grid-wrapper .grid-cell div.entries a:visited:hover {
            color: #0071c4;
            text-decoration: none;
        }
        </style>';
        $form->Fields()->push(LiteralField::create('ShortCuts', $html));
    }

    protected function getLinksFromImplementor()
    {
        $array = [];
        $useDefaultDashboard = $this->config()->get('use_default_dashboard');
        $classNames = ClassInfo::implementorsOf(DashboardWelcomeQuickLinksProvider::class);
        foreach ($classNames as $className) {
            if($useDefaultDashboard === false && $className === DefaultDashboardProvider::class) {
                continue;
            }
            $array += Injector::inst()->get($className)->provideDashboardWelcomeQuickLinks();
        }
        return $array;
    }

    protected function makeShortCut(string $title, string $link, ?string $onclick = '', ?string $script = '', ?string $style = '', ?string $iconClass = '', ?string $target = '')
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
            <h2 style="' . $style . '">
                ' . $icon . '<a href="' . $link . '" id="' . $name . '" ' . $target . ' ' . $onclick . '>' . $title . '</a>
            </h2>';
        } else {
            $html = '
            ' . $script . '
            <p>
                &raquo; ' . $title . '
            </p>
            ';
        }
        if ($style) {
            $html .= '<style>' . $style . '</style>';
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
