#!/bin/bash

# Script de Verificação do Projeto Sem Composer
# Valida se o projeto está pronto para usar sem dependências do Composer

echo "🔍 Verificando Integridade do Projeto..."
echo "========================================"

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Verificações
ERRORS=0
WARNINGS=0

echo ""
echo "📦 Verificando estrutura de diretórios..."
REQUIRED_DIRS=(
    "app/controllers"
    "app/models"
    "app/services"
    "app/middleware"
    "app/helpers"
    "app/libraries"
    "app/views"
    "config"
    "database"
    "public"
    "routes"
)

for dir in "${REQUIRED_DIRS[@]}"; do
    if [ -d "$PROJECT_DIR/$dir" ]; then
        echo -e "${GREEN}✓${NC} $dir"
    else
        echo -e "${RED}✗${NC} $dir (FALTANDO)"
        ((ERRORS++))
    fi
done

echo ""
echo "📄 Verificando arquivos críticos..."
REQUIRED_FILES=(
    "public/index.php"
    "config/env.php"
    "config/app.php"
    "config/database.php"
    "app/Database.php"
    "app/Router.php"
    "app/libraries/Jwt.php"
    "app/libraries/Smtp.php"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$PROJECT_DIR/$file" ]; then
        echo -e "${GREEN}✓${NC} $file"
    else
        echo -e "${RED}✗${NC} $file (FALTANDO)"
        ((ERRORS++))
    fi
done

echo ""
echo "❌ Verificando se composer.json foi removido..."
if [ ! -f "$PROJECT_DIR/composer.json" ]; then
    echo -e "${GREEN}✓${NC} composer.json não existe (correto)"
else
    echo -e "${YELLOW}⚠${NC} composer.json ainda existe"
    ((WARNINGS++))
fi

if [ ! -f "$PROJECT_DIR/composer.lock" ]; then
    echo -e "${GREEN}✓${NC} composer.lock não existe (correto)"
else
    echo -e "${YELLOW}⚠${NC} composer.lock ainda existe"
    ((WARNINGS++))
fi

echo ""
echo "📁 Verificando se vendor foi removido..."
if [ ! -d "$PROJECT_DIR/vendor" ]; then
    echo -e "${GREEN}✓${NC} vendor não existe (correto)"
else
    echo -e "${YELLOW}⚠${NC} vendor ainda existe (pode ser removido)"
    ((WARNINGS++))
fi

echo ""
echo "🔍 Verificando imports do Composer..."
COMPOSER_IMPORTS=$(grep -r "require.*vendor/autoload" "$PROJECT_DIR/public" "$PROJECT_DIR/app" 2>/dev/null | grep -v ".git" || true)
if [ -z "$COMPOSER_IMPORTS" ]; then
    echo -e "${GREEN}✓${NC} Nenhum import do vendor encontrado"
else
    echo -e "${RED}✗${NC} Imports do Composer encontrados:"
    echo "$COMPOSER_IMPORTS"
    ((ERRORS++))
fi

echo ""
echo "========================================"
if [ $ERRORS -eq 0 ]; then
    if [ $WARNINGS -eq 0 ]; then
        echo -e "${GREEN}✅ Projeto está pronto sem Composer!${NC}"
        exit 0
    else
        echo -e "${YELLOW}⚠️  Projeto está pronto, mas com $WARNINGS avisos${NC}"
        exit 0
    fi
else
    echo -e "${RED}❌ Projeto tem $ERRORS erro(s) que precisam ser corrigidos${NC}"
    exit 1
fi
