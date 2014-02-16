# AmazonS3

The AmazonS3 module allows the local file system to be replaced with S3. Uploads are saved into the Drupal file table using D7's file/stream wrapper system.

You can also use it with other [S3 compatible cloud storage services](http://en.wikipedia.org/wiki/Amazon_S3#S3_API_and_competing_services) such as [Google Cloud Storage](https://cloud.google.com/storage).

You can switch it on as the default file system scheme, or individually for file and image fields.

## Requirements
- [AWSSDK for PHP](http://drupal.org/project/awssdk) (Drupal module)
- [Libraries API](http://drupal.org/project/libraries)
- [AWS SDK for PHP 1.6.2](http://pear.amazonwebservices.com/get/sdk-1.6.2.tgz)


You will need to set allow_url_fopen to on your PHP settings. This option enables the URL-aware fopen wrappers that enable accessing URL object like files. See [http://uk.php.net/manual/en/function.fopen.php](http://uk.php.net/manual/en/function.fopen.php)

## Known Issues
### SSL
Some curl libraries, such as the one bundled with MAMP, do not come with authoritative certificate files.

[http://dev.soup.io/post/56438473/If-youre-using-MAMP-and-doing-something](http://dev.soup.io/post/56438473/If-youre-using-MAMP-and-doing-something)

### Bucket names with "." in them
Newer versions of OpenSSL also have issues with buckets with "." in their names. Bucket names with dots in them will use a slightly different path when delivering files over SSL. This is only relevant if you're not using a CNAME to alias a bucket to your own domain. See https://forums.aws.amazon.com/thread.jspa?threadID=69108&start=0&tstart=0#308166

### Image Styles
Image styles are delivered first through the private file system, which generates a derivative on S3 and is served from S3 thereafter. Don't expect it to be fast the first time!

## Installation
- Download and install Libraries, AWS SDK and AmazonS3 Drupal modules.
- Configure AWS SDK at /admin/config/media/awssdk
- Configure your bucket setttings at /admin/config/media/amazon

## Usage

- Change individual fields to upload to S3 in the field settings
- Use AmazonS3 instead of the public file system (although there are a few issues due to core hardcoding the use of public:// in a few places e.g. aggregated CSS and JS). Go to /admin/config/media/file-system and set the default download method to Amazon.

## CORS Upload
See [http://drupal.org/project/amazons3_cors](http://drupal.org/project/amazons3_cors)


## API
You can modify the generated URL and it's properties, this is very useful for setting Cache-Control and Expires headers (as long as you aren't using CloudFront).

You can also alter the metadata for each object saved to S3 with hook_amazons3_save_headers(). This is very useful for forcing the content-disposition header to force download files if they're being delivered through CloudFront presigned URLs.

See amazons3.api.php