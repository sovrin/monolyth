install: install-backend install-frontend

install-backend:
	@cd backend && composer install
	@cd backend && composer run workspaces:install

install-frontend:
	@cd frontend && npm ci

run-backend-core:
	@cd backend/apps/core && composer run app

run-frontend-core:
	@cd frontend/apps/core && npm run app

APP:=

deploy:
	@cd backend/tools && php deploy.php deploy $(APP)
