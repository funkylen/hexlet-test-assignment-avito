setup:
	cp .env.local .env
	docker-compose build
	docker-compose run app composer install
	docker-compose up -d
	sleep 2
	docker-compose run app php artisan migrate
