FROM debian:latest

RUN apt -y update
RUN apt -y upgrade
RUN apt -y install php composer php-sqlite3 php-xml php-json texlive latexmk

COPY . /basildon/
RUN composer install --no-dev --optimize-autoloader --working-dir=/basildon/

ENV PATH="$PATH:/basildon/bin"

WORKDIR /project

ENTRYPOINT [ "basildon" ]
