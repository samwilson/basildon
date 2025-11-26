FROM debian:latest
MAINTAINER Sam Wilson sam@samwilson.id.au

RUN apt -y update
RUN apt -y upgrade
RUN apt -y install php composer php-sqlite3 php-xml php-json texlive latexmk
RUN composer global require samwilson/basildon
ENV PATH="$PATH:/root/.config/composer/vendor/bin/"

WORKDIR /basildon/

COPY . /basildon/

ENTRYPOINT [ "/root/.config/composer/vendor/bin/basildon" ]
CMD [ "" ]
