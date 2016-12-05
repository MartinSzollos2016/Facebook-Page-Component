<?php

namespace FacebookPageComponent;

use Nette;
use Tracy\Debugger;

/**
 * Založeno na https://cs.wordpress.org/plugins/easy-facebook-feed/
 * Class FacebookPage
 * @package FacebookPageComponent
 */
class FacebookPage extends Nette\Application\UI\Control
{


    private static $initiated = false;

    private static $useFopen = false;
    private static $images = array();

    /** @var  Nette\Localization\ITranslator */
    protected $translator;

    /** @var  string */
    protected $templateFile;

    /** @var bool */
    private $useCurl = FALSE;

    const ACCESS_TOKEN = '1492018151012834|U3qsH98pUZxv5watRRC4c-rg1rc';
    const PAGE_INFO_URL = "https://graph.facebook.com/%s/%s?fields=%s&access_token=%s";
    const PAGE_FEED_URL = "https://graph.facebook.com/%s/%s/posts?fields=%s&access_token=%s&limit=%s";

    private static $graph_api_versions = [
        "v2.4",
        "v2.5",
        "v2.6",
        "v2.7",
        "v2.8",
    ];

    /** @var string */
    private $graphApiVersion = "v2.8";
    /** @var string */
    private $pageId = "bbcnews";

    /** @var null */
    private $postLimit = NULL;

    public function __construct(Nette\Localization\ITranslator $translator, Nette\ComponentModel\IContainer $parent, $name = NULL)
    {
        parent::__construct($parent, $name);
        $this->translator = $translator;
        $this->templateFile = __DIR__ . "/templates/default.latte";
    }

    /**
     * Use curl to get content
     * @param bool $useCurl
     */
    private function setCurl($useCurl = TRUE)
    {
        $this->useCurl = boolval($useCurl);
    }

    /**
     * Select graph api version v2.4 .. v2.8
     * @param $graphApiVersion
     */
    public function setGraphApiVersion($graphApiVersion = "v2.8")
    {
        if (in_array($graphApiVersion, self::$graph_api_versions)) {
            $this->graphApiVersion = $graphApiVersion;
        }
    }

    /**
     * Enter page ID
     * @param $pageId
     */
    public function setPageId($pageId)
    {
        $this->pageId = $pageId;
    }

    /**
     * Set Maximum posts
     * @param null $postLimit
     */
    public function setPostLimit($postLimit = NULL)
    {
        if (is_numeric($postLimit) and intval($postLimit) > 0) {
            $this->postLimit = $postLimit;
        }
    }

    /**
     * Render komponenty
     */
    public function render()
    {
        $this->template->setFile($this->templateFile);

        $pageId = $this->pageId;

        $page = $this->getPageInfo($pageId);
        $feed = $this->getPageFeed($pageId, $this->postLimit);

        if (isset($feed->error)) {
            $this->template->setFile(__DIR__ . "/templates/error.latte");
            $this->template->errorMessage = $feed->error->message;
        } elseif (isset($page->error)) {
            $this->template->setFile(__DIR__ . "/templates/error.latte");
            $this->template->errorMessage = $page->error->message;
        }else{
            $this->prepareFeedData($page, $feed);
        }



        $this->template->render();
    }

    /**
     * @param $pageId
     * @return array|mixed|object
     */
    private function getPageInfo($pageId)
    {
        $fields = 'link,name,cover,picture';
        $url = sprintf(self::PAGE_INFO_URL, $this->graphApiVersion, $pageId, $fields, self::ACCESS_TOKEN);
        $page = $this->getConnection($url);

        return $page;
    }

    /**
     * @param $pageId
     * @param $postLimit
     * @return array|mixed|object
     */
    private function getPageFeed($pageId, $postLimit)
    {
        $fields = 'full_picture,picture,type,message,link,name,description,from,source,created_time';
        $url = sprintf(self::PAGE_FEED_URL, $this->graphApiVersion, $pageId, $fields, self::ACCESS_TOKEN, $postLimit);
        $feed = $this->getConnection($url);

        return $feed;
    }

    /**
     * @param $url
     * @return array|mixed|object
     */
    private function getConnection($url)
    {
        $json = '';

        // use curl or file_get_contents
        if ($this->useCurl) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $json = curl_exec($ch);
            curl_close($ch);
        } else {
            if (file_get_contents($url)) {
                $json = file_get_contents($url);
            } else {
                $arr = array('error' => array('message' => "Unknown file_get_contents connection error with Facebook."));
                $json = json_encode($arr);
            }
        }

        return json_decode($json);
    }

    /**
     * @param $message
     * @return mixed
     */
    private static function setHashtags($message)
    {
        if (preg_match_all("/#(\\w+)/", $message, $matches)) {
            foreach ($matches[1] as $key => $match) {
                $url = "<a href='https://www.facebook.com/hashtag/" . $match . "'>#" . $match . "</a>";
                $message = str_replace('#' . $match, $url, $message);
            }
        }

        return $message;
    }

    /**
     * @param $message
     * @return mixed
     */
    private static function setUrls($message)
    {
        $message = preg_replace("/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/", "<a href=\"\\0\">\\0</a>", $message);

        return $message;
    }


    /**
     * @param $datetime
     * @param bool|false $full
     * @return string
     */
    public static function eff_time_elapsed_string($datetime, $full = false)
    {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = (array)$now->diff($ago);

        $diff['w'] = floor($diff['d'] / 7);
        $diff['d'] -= $diff['w'] * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );

        foreach ($string as $k => &$v) {
            if ($diff[$k]) {
                $v = $diff[$k] . ' ' . $v . ($diff[$k] > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) {
            $string = array_slice($string, 0, 1);
        }

        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }

    /**
     * @param $page
     * @param $feed
     */
    public function prepareFeedData($page, $feed)
    {
        $this->template->pageLink = $page->link;
        $this->template->pageCoverSource = $page->picture->data->url;

        $feedArray = [];
        foreach ($feed->data as $key => $data) {

            $existsMessage = property_exists($data, 'message');

            if ($existsMessage) {
                $message = self::setUrls($data->message);
                $message = self::setHashtags($message);
            }

            $feedArray[$key] = [
                'dataFromName' => $data->from->name,
                'dataLink' => isset($data->link) ? $data->link : $page->link,
                'dataCreatedTime' => self::eff_time_elapsed_string($data->created_time),
                'dataMessage' => $existsMessage ? nl2br($message) : NULL,
                'dataType' => $data->type,

            ];

            switch ($data->type) {
                case 'photo':
                    $feedArray[$key]['imageUrl'] = $data->full_picture;
                    break;
                case 'link':
                    $feedArray[$key]['dataLink'] = $data->link;
                    $feedArray[$key]['dataName'] = $data->name;
                    $feedArray[$key]['dataDescription'] = $data->description;
                    if ($data->full_picture) {
                        $feedArray[$key]['dataPicture'] = $data->full_picture;
                    }
                    break;
                case 'video':
                    if (strpos($data->source, 'fbcdn')) {
                        $feedArray[$key]['dataSource'] = $data->source;
                        $feedArray[$key]['dataPicture'] = property_exists($data, 'picture') ? $data->picture : $data->full_picture;
                        $feedArray[$key]['dataLink'] = $data->full_picture;
                    } else {
                        $feedArray[$key]['imageUrl'] = $data->full_picture;
                        $feedArray[$key]['dataType'] = 'photo';
                    }
                    break;
                case 'event':
                    $feedArray[$key]['dataLink'] = $data->link;
                    $feedArray[$key]['dataName'] = $data->name;
                    $feedArray[$key]['dataDescription'] = $data->description;
                    if ($data->full_picture) {
                        $feedArray[$key]['dataPicture'] = $data->full_picture;
                    }
                    break;
                case 'status':
                    break;
            }

        }

        $hash = Nette\Utils\ArrayHash::from($feedArray);
        $this->template->feeds = $hash;
    }

}