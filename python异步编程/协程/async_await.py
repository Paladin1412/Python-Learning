async def downloader(url):
    return "m"


async def download_url(url):
    html = await downloader(url)
    return html


if __name__ == "__main__":
    coro = download_url("www")
    coro.send(None)
