def decorate(func):
    print("Begin")
    func()
    print("End")
def target():
    print("running target()")
decorate(target)
# equal below function
@decorate
def target():
    print("running target()")


