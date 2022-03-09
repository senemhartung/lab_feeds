<?php

namespace Drupal\lab_feeds;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Feed fetcher from different sources.
 */
class FeedFetcher {

  /**
   * Fetch feed and create nodes.
   */
  public function fetchFeed() {
    $client = new Client();

    // How many feeds to grab from each lab.
    $number = 2;

    // https://www6.slac.stanford.edu/news/news-center.aspx
    $slac = $this->slac($client, $number);
    $this->createFeedNodes($slac, $number);

    // https://news.fnal.gov/newsroom/news.
    $fermilab = $this->fermilab($client, $number);
    $this->createFeedNodes($fermilab, $number);

  }

  /**
   * Fetch https://www6.slac.stanford.edu/news/news-center.aspx.
   */
  public function slac($client, $number) {
    $crawler = $client->request('GET', 'https://www6.slac.stanford.edu/news/news-center.aspx');

    $initial_filter = $crawler->filter('.view-news-center .views-row');
    if ($initial_filter->count()) {
      $feeds = $initial_filter->each(function (Crawler $node, $i) {
        // Make sure the urls are full urls.
        $link_filter = $node->filter('.title a');
        if ($link_filter->count()) {
          $link = $link_filter->attr('href');
          if (substr($link, 0, 4) !== 'http') {
            $link = 'https://www6.slac.stanford.edu/' . $link;
          }
        }
        else {
          $link = '';
        }

        return [
          'title' => count($node->filter('.title')) ? trim($node->filter('.title')->text()) : '',
          'link' => $link,
          'date' => count($node->filter('.date')) ? date('Y-m-d', strtotime(trim($node->filter('.date')->text()))) : '',
          'desc' => count($node->filter('.field-name-field-teaser')) ? trim($node->filter('.field-name-field-teaser')->text()) : '',
          'image' => count($node->filter('img')) ? $node->filter('img')->attr('src') : '',
          'source' => 'slac.stanford.edu',
        ];
      });

      return array_slice($feeds, 0, $number);
    }
    else {
      return [];
    }
  }

  /**
   * Fetch https://news.fnal.gov/newsroom/news.
   */
  public function fermilab($client, $number) {
    $crawler = $client->request('GET', 'https://news.fnal.gov/newsroom/news/');
    $initial_filter = $crawler->filter('.fnal-article');
    if ($initial_filter->count()) {
      $feeds = $crawler->filter('.fnal-article')->each(function (Crawler $node, $i) {
        // Make sure the urls are full urls.
        $link = count($node->filter('.entry-title a')) ? $node->filter('.entry-title a')->attr('href') : '';
        $image = count($node->filter('img')) ? $node->filter('img')->attr('src') : '';

        return [
          'title' => count($node->filter('.entry-title h4')) ? trim($node->filter('.entry-title h4')->text()) : '',
          'link' => $link,
          'date' => count($node->filter('.entry-meta  .published > span')) ? date('Y-m-d', strtotime(trim($node->filter('.entry-meta  .published > span')->first()->text()))) : '',
          'desc' => count($node->filter('.entry-description')) ? trim($node->filter('.entry-description')->text()) : '',
          'image' => $image,
          'source' => 'news.fnal.gov',
        ];
      });
      return array_slice($feeds, 0, $number);
    }
    else {
      return [];
    }
  }

  /**
   * Create feed nodes.
   */
  public function createFeedNodes($feeds, $number) {

    foreach ($feeds as $feed) {
      // Check if we already grabbed this feed item.
      $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
      $query->condition('type', 'lab_feed');
      $query->condition('field_feed_link', $feed['link']);
      $nid = $query->execute();

      // Save if it does not exist.
      if (empty($nid)) {
        // Get the image.
        if (!file_exists('public://feed_images/')) {
          mkdir('public://feed_images/', 0777, TRUE);
        }
        $file = system_retrieve_file($feed['image'], 'public://feed_images/', TRUE);

        // Create the node.
        $node = Node::create([
          'type' => 'lab_feed',
          'uid' => 1,
          'status' => 1,
          'moderation_state' => 'published',
          'title' => $feed['title'],
          'body' => $feed['desc'],
          'field_feed_date' => [
            $feed['date'],
          ],
          'field_feed_link' => [
            $feed['link'],
          ],
          'field_image_url' => [
            $feed['image'],
          ],
          'field_feed_image' => [
            'target_id' => $file->id(),
            'alt' => $feed['title'],
            'title' => $feed['title'],
          ],
          'field_feed_source' => [
            $feed['source'],
          ],
        ]);

        $node->save();
      }

      // Delete the old nodes if any.
      $nids = \Drupal::entityQuery("node")
        ->condition('type', 'lab_feed')
        ->condition('field_feed_source', $feed['source'])
        ->sort('field_feed_date', 'DESC')
        ->execute();
      $nids = array_slice($nids, $number);
      $storage_handler = \Drupal::entityTypeManager()->getStorage("node");
      $entities = $storage_handler->loadMultiple($nids);

      // Delete the image files.
      foreach ($entities as $e) {
        $fid = $e->get('field_feed_image')->target_id;
        $file = File::load($fid);
        $file->delete();
      }

      // Delete the nodes.
      $storage_handler->delete($entities);

    }
  }

}
