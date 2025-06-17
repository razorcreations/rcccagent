# Razor Certbot Certificate Checker Agent

## Installation

### Clone the repository

```bash
git clone git@github.com:razorcreations/rcccagent.git /opt/rcccagent
chmod +x /opt/rcccagent/agent.php
```

### Setup the service

```
sudo cp /opt/rcccagent/rcccagent.service /etc/systemd/system
sudo systemctl daemon-reexec
sudo systemctl daemon-reload
sudo systemctl enable rcccagent.service
sudo systemctl start rcccagent.service
```

## Agent Control

```bash
# Get the agent status
sudo systemctl status rcccagent

# Control the agent
sudo systemctl restart|start|stop rcccagent

# Disable the agent
sudo systemctl stop rcccagent
sudo systemctl disable rcccagent.service
sudo systemctl daemon-reload
```

