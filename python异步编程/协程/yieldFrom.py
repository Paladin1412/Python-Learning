from itertools import chain

my_list=[1,2,3]
my_dict={
    "a":"a1",
    "b":"b1"
}
# for value in chain(my_list,my_dict,range(5,10)):
#     print(value)

#yield from iterable
# def my_chain(*args,**kwargs):
#     for my_iterable in args:
#         yield from my_iterable
# for value in my_chain(my_list,my_dict,range(5,10)):
#     print(value)

def g1(iterable):
    yield iterable
def g2(iterable):
    yield from iterable
for v in g1(range(10)):
    print(v)
for v in g2(range(10)):
    print(v)
#yield from会在调用方与子生成器之间建立一个双向通道


