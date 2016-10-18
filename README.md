
heroku git:remote -a rockwell-line
heroku config:set BUILDPACK_URL=https://github.com/heroku/heroku-buildpack-php
heroku config:add TZ=Asia/Tokyo

heroku config:add LINE_CHANNEL_SECRET=<YOUR_CHANNEL_SECRET>
heroku config:add LINE_CHANNEL_ACCESS_TOKEN=<YOUR_CHANNEL_ACCESS_SECRET>