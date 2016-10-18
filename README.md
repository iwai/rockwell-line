
heroku git:remote -a rockwell-line
heroku config:set BUILDPACK_URL=https://github.com/heroku/heroku-buildpack-php
heroku config:add TZ=Asia/Tokyo