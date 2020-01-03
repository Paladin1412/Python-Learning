def gen_ab():
    print("start")
    yield 'a'
    print('continue')
    yield 'b'
    print('end')

# for index,c in enumerate(gen_ab()):
#     print("index",index)
#     print("->",c)

r = list(map(lambda a,b:(a,b),range(11),[2,4,8]))
print(r)