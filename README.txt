script.php: Run this in as cron job
test.php: for testing only

What does script.php do?
# 1) Loads table/field definitions from config
# 2) finds all <img> tags in those fields
# 3) if download_url() returns true on url
#    download the image to store_path() and replace src attribute by new_url()
#  Those 3 functions must be defined in config.php, see config.php.sample

LICENSE LGPL or whatever you think is appropriate
