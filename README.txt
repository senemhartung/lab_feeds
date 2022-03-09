Challenge
================================

Grab public news items from websites of certain physics labs who do not provide rss feeds. 

Only two labs are handled as a sample in this module.

Requirements
------------

Goutte PHP Web Scraper - "fabpot/goutte" as a require dependency in Drupal "composer.json" file.

A Drupal content type called 'Lab Feeds' with the following fields:

    - Title
    - Body
    - Feed Date (Date only)
    - Feed Link (No link text)
    - Feed Image URL (No link text)
    - Feed Image (image)
    - Feed Source (text)

Approach
------------

- Scrape the given lab news urls on cron run and grab 2 most recent news items for each lab.
- Save the image associated with the news item if it was not previously saved.
- Save the news item as a 'Lab Feed' node if it was not previously saved.
- Delete the old nodes and old images that are not the most recent 2 anymore. 

Further Steps
-------------

- Display 'Lab Feed' nodes in a view as a slideshow.
- An example can be seen on https://www.interactions.org/, under 'From the Labs' section.
