# Guia de Compilação do Agente

Este guia detalha como compilar o agente de backup em um executável standalone do Windows.

---

## 📦 Método 1: Usando PS2EXE (Recomendado)

### Instalação

```powershell
# Instalar o módulo PS2EXE
Install-Module -Name ps2exe -Scope CurrentUser -Force

# Verificar instalação
Get-Command Invoke-ps2exe
```

### Compilação Básica

```powershell
# Navegar para a pasta do agente
cd C:\BackupAgent

# Compilar com console visível (para debug)
Invoke-ps2exe `
    -inputFile ".\BackupAgent.ps1" `
    -outputFile ".\BackupAgent.exe" `
    -noConsole:$false `
    -requireAdmin `
    -title "Backup WebManager Agent" `
    -description "Agente de coleta automática de dados de backup" `
    -company "Sua Empresa" `
    -product "Backup WebManager" `
    -version "1.0.0.0" `
    -copyright "(c) 2026 Sua Empresa"
```

### Compilação Avançada

```powershell
# Compilar com ícone personalizado e sem console
Invoke-ps2exe `
    -inputFile ".\BackupAgent.ps1" `
    -outputFile ".\BackupAgent.exe" `
    -iconFile ".\icon.ico" `
    -noConsole:$true `
    -noOutput:$false `
    -noError:$false `
    -requireAdmin `
    -credentialGUI `
    -supportOS `
    -virtualize `
    -longPaths `
    -title "Backup WebManager Agent" `
    -description "Agente de coleta automática de dados de backup" `
    -company "Sua Empresa" `
    -product "Backup WebManager" `
    -version "1.0.0.0" `
    -copyright "(c) 2026 Sua Empresa"
```

### Parâmetros Importantes

| Parâmetro | Descrição |
|-----------|-----------|
| `-noConsole:$false` | Mantém janela de console (útil para debug) |
| `-noConsole:$true` | Oculta janela de console (produção) |
| `-requireAdmin` | Requer elevação de privilégios |
| `-iconFile` | Define ícone do executável |
| `-longPaths` | Suporte a caminhos longos do Windows |
| `-supportOS` | Adiciona manifesto de compatibilidade |
| `-virtualize` | Virtualização de UAC |

---

## 🔧 Método 2: Usando IExpress (Nativo do Windows)

O IExpress é uma ferramenta nativa do Windows para criar executáveis auto-extraíveis.

### Passo 1: Preparar os arquivos

```powershell
# Criar pasta temporária
New-Item -ItemType Directory -Path "C:\Temp\BackupAgentBuild" -Force

# Copiar arquivos necessários
Copy-Item "C:\BackupAgent\BackupAgent.ps1" -Destination "C:\Temp\BackupAgentBuild\"
Copy-Item "C:\BackupAgent\modules" -Destination "C:\Temp\BackupAgentBuild\" -Recurse
Copy-Item "C:\BackupAgent\config\config.example.json" -Destination "C:\Temp\BackupAgentBuild\"

# Criar script wrapper
@'
@echo off
PowerShell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0BackupAgent.ps1" %*
'@ | Out-File "C:\Temp\BackupAgentBuild\BackupAgent.bat" -Encoding ASCII
```

### Passo 2: Criar arquivo SED para IExpress

```powershell
@'
[Version]
Class=IEXPRESS
SEDVersion=3

[Options]
PackagePurpose=InstallApp
ShowInstallProgramWindow=1
HideExtractAnimation=0
UseLongFileName=1
InsideCompressed=0
CAB_FixedSize=0
CAB_ResvCodeSigning=0
RebootMode=N
InstallPrompt=%InstallPrompt%
DisplayLicense=%DisplayLicense%
FinishMessage=%FinishMessage%
TargetName=%TargetName%
FriendlyName=%FriendlyName%
AppLaunched=%AppLaunched%
PostInstallCmd=%PostInstallCmd%
AdminQuietInstCmd=%AdminQuietInstCmd%
UserQuietInstCmd=%UserQuietInstCmd%
SourceFiles=SourceFiles

[Strings]
InstallPrompt=Instalar Backup WebManager Agent?
DisplayLicense=
FinishMessage=Instalação concluída!
TargetName=C:\Temp\BackupAgentSetup.exe
FriendlyName=Backup WebManager Agent
AppLaunched=BackupAgent.bat
PostInstallCmd=<None>
AdminQuietInstCmd=
UserQuietInstCmd=
FILE0="BackupAgent.ps1"
FILE1="BackupAgent.bat"

[SourceFiles]
SourceFiles0=C:\Temp\BackupAgentBuild\

[SourceFiles0]
%FILE0%=
%FILE1%=
'@ | Out-File "C:\Temp\BackupAgent.sed" -Encoding ASCII
```

### Passo 3: Compilar com IExpress

```powershell
# Executar IExpress
iexpress /N "C:\Temp\BackupAgent.sed"
```

---

## 🎯 Método 3: Criar Instalador MSI (Avançado)

Para criar um instalador MSI profissional, use o **WiX Toolset**.

### Instalação do WiX

```powershell
# Baixar e instalar WiX Toolset v3.11
# https://wixtoolset.org/releases/

# Ou via Chocolatey
choco install wixtoolset -y
```

### Exemplo de arquivo WXS

Crie um arquivo `BackupAgent.wxs`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Wix xmlns="http://schemas.microsoft.com/wix/2006/wi">
  <Product Id="*" 
           Name="Backup WebManager Agent" 
           Language="1033" 
           Version="1.0.0.0" 
           Manufacturer="Sua Empresa" 
           UpgradeCode="PUT-GUID-HERE">
    
    <Package InstallerVersion="200" 
             Compressed="yes" 
             InstallScope="perMachine" />

    <MajorUpgrade DowngradeErrorMessage="A newer version is already installed." />
    <MediaTemplate EmbedCab="yes" />

    <Feature Id="ProductFeature" Title="Backup Agent" Level="1">
      <ComponentGroupRef Id="ProductComponents" />
    </Feature>
  </Product>

  <Fragment>
    <Directory Id="TARGETDIR" Name="SourceDir">
      <Directory Id="ProgramFilesFolder">
        <Directory Id="INSTALLFOLDER" Name="BackupAgent" />
      </Directory>
    </Directory>
  </Fragment>

  <Fragment>
    <ComponentGroup Id="ProductComponents" Directory="INSTALLFOLDER">
      <Component Id="BackupAgentScript" Guid="PUT-GUID-HERE">
        <File Id="BackupAgentPS1" Source="BackupAgent.ps1" KeyPath="yes"/>
      </Component>
      <!-- Adicione mais componentes para outros arquivos -->
    </ComponentGroup>
  </Fragment>
</Wix>
```

### Compilar o MSI

```powershell
# Compilar
candle.exe BackupAgent.wxs

# Linkar
light.exe BackupAgent.wixobj -out BackupAgentSetup.msi
```

---

## ✅ Validação do Executável

Após compilar, teste o executável:

```powershell
# Verificar informações do arquivo
Get-ItemProperty "C:\BackupAgent\BackupAgent.exe" | Select-Object *

# Testar execução
C:\BackupAgent\BackupAgent.exe -RunOnce -TestMode

# Verificar assinatura digital (se aplicável)
Get-AuthenticodeSignature "C:\BackupAgent\BackupAgent.exe"
```

---

## 📝 Assinatura Digital

Para produção, assine o executável com certificado code signing:

```powershell
# Assinar com certificado
Set-AuthenticodeSignature -FilePath "C:\BackupAgent\BackupAgent.exe" `
    -Certificate (Get-ChildItem Cert:\CurrentUser\My -CodeSigningCert) `
    -TimestampServer "http://timestamp.digicert.com"

# Verificar assinatura
Get-AuthenticodeSignature "C:\BackupAgent\BackupAgent.exe" | Format-List *
```

---

## 📦 Distribuição

### Opção 1: Arquivo ZIP

```powershell
# Criar pacote de distribuição
Compress-Archive -Path "C:\BackupAgent\*" `
    -DestinationPath "C:\Temp\BackupAgent-v1.0.0.zip" `
    -CompressionLevel Optimal
```

### Opção 2: Script de Deploy Remoto

```powershell
# Deploy para múltiplos servidores
$servers = @("SRV01", "SRV02", "SRV03")
$sourcePath = "C:\BackupAgent"

foreach ($server in $servers) {
    # Copiar arquivos
    Copy-Item -Path $sourcePath `
        -Destination "\\$server\C$\BackupAgent" `
        -Recurse -Force
    
    # Executar instalação remota
    Invoke-Command -ComputerName $server -ScriptBlock {
        & "C:\BackupAgent\Install-BackupAgent.ps1" `
            -ApiUrl "https://dev.gortan.com.br/world/bkpmng" `
            -ApiKey "api-key-aqui" `
            -ServerName $env:COMPUTERNAME
    }
}
```

### Opção 3: GPO (Group Policy)

1. Copie o instalador para um compartilhamento de rede
2. Crie uma GPO para distribuir via:
   - **Computer Configuration** → **Software Settings** → **Software Installation**
3. Adicione o pacote MSI ou script de instalação
4. Configure para instalação automática

---

## 🔒 Considerações de Segurança

### Proteção contra Antivírus

Executáveis compilados de PowerShell podem ser sinalizados por antivírus. Para evitar:

1. **Assine digitalmente** o executável
2. **Adicione exceção** no antivírus corporativo
3. **Use compiladores alternativos** como:
   - PowerShell Pro Tools
   - Advanced Installer

### Ofuscação de Código

Para proteger a lógica do script:

```powershell
# Exemplo com PS2EXE - não ofusca o código original
# Use ferramentas dedicadas como:
# - PowerShell Obfuscator
# - Invoke-Obfuscation
```

---

## 🆘 Troubleshooting

### Erro: "Script não pode ser carregado"

**Causa:** Política de execução do PowerShell

**Solução:**
```powershell
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process
```

### Erro: "Módulos não encontrados"

**Causa:** Caminhos relativos após compilação

**Solução:** Use `$PSScriptRoot` nos scripts para caminhos relativos

### Executável muito grande

**Causa:** PS2EXE inclui runtime do PowerShell

**Solução:**
- Use `-noConsole` se não precisar de console
- Remova dependências desnecessárias
- Use compressão UPX (não recomendado para produção)

---

## 📚 Recursos Adicionais

- [PS2EXE GitHub](https://github.com/MScholtes/PS2EXE)
- [WiX Toolset](https://wixtoolset.org/)
- [Microsoft Code Signing](https://docs.microsoft.com/en-us/windows-hardware/drivers/dashboard/code-signing-cert-manage)

---

## 🔄 Atualização do Agente

Para atualizar agentes já instalados:

```powershell
# Script de atualização remota
$servers = Get-Content "C:\servers.txt"
$newVersion = "C:\BackupAgent-v1.1.0.zip"

foreach ($server in $servers) {
    # Para tarefa agendada
    Stop-ScheduledTask -TaskName "BackupWebManager-Agent" -CimSession $server
    
    # Fazer backup da config antiga
    Copy-Item "\\$server\C$\BackupAgent\config\config.json" `
        -Destination "\\$server\C$\BackupAgent\config\config.json.bak"
    
    # Atualizar arquivos
    Expand-Archive -Path $newVersion -DestinationPath "\\$server\C$\BackupAgent" -Force
    
    # Restaurar config
    Copy-Item "\\$server\C$\BackupAgent\config\config.json.bak" `
        -Destination "\\$server\C$\BackupAgent\config\config.json" -Force
    
    # Reiniciar tarefa
    Start-ScheduledTask -TaskName "BackupWebManager-Agent" -CimSession $server
}
```
