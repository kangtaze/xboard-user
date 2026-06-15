files = [
    "/www/app/Protocols/General.php",
    "/www/app/Protocols/Clash.php",
    "/www/app/Protocols/ClashMeta.php",
    "/www/app/Protocols/SingBox.php",
    "/www/app/Protocols/QuantumultX.php",
    "/www/app/Protocols/Stash.php",
]
for f in files:
    with open(f) as fh:
        content = fh.read()
    if "id={$user['id']}" not in content:
        content = content.replace(
            "expire={$user['expired_at']}\"",
            "expire={$user['expired_at']}; id={$user['id']}\""
        )
        with open(f, "w") as fh:
            fh.write(content)
        print("Updated: " + f)
    else:
        print("Already updated: " + f)
