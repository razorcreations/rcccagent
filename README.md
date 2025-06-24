# Razor Certbot Certificate Checker Agent

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

