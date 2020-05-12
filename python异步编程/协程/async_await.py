# import types
#
#
# @types.coroutine
# def downloader(url):
#     yield 'm'
#
# # async def downloader(url):
# #     return "m"
#
#
# async def download_url(url):
#     html = await downloader(url)
#     return html
#
#
# if __name__ == "__main__":
#     coro = download_url("www")
#     coro.send(None)

def y():
    yield 1
def y2():
    a = yield from y()
    print(a)

y2().send(None)
next(y2())