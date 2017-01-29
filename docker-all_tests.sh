#!/usr/bin/env bash
# This script just runs the containers in a way that failures are observable to travis.

docker-compose rm -f ; docker-compose up

echo ""
echo "Run summary:"
docker-compose ps -q | xargs docker inspect -f '{{ .Name }} exited with code {{ .State.ExitCode }}'
failed_containers=`docker-compose ps -q | xargs docker inspect -f '{{ .State.ExitCode }}' | grep -v '^0$' | wc -l`
if [ $failed_containers -eq '0' ]
then
  echo
  echo " *** Tests passed against all versions. ***"
  exit 0
else
  echo ""
  >&2 echo "Tests failed in $failed_containers container(s)."
  >&2 echo "See complete output above for details."
  exit 1
fi
